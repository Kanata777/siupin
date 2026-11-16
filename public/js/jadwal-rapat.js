/* Halaman Jadwal Rapat — SIUPIN */
(function () {
  'use strict';

  var J = window.JADWAL || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function urlRapat(id) { return J.urlShow.replace('__ID__', encodeURIComponent(id)); }
  function teks(id, nilai) { $(id).textContent = nilai == null || nilai === '' ? '-' : nilai; }

  /* ---------- Filter ---------- */
  var form = $('#formFilter');
  $$('select', form).forEach(function (s) { s.addEventListener('change', function () { form.submit(); }); });
  $('#fCari').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); form.submit(); } });
  $('#fCari').addEventListener('change', function () { form.submit(); });

  var elDari = $('#fDari'), elSampai = $('#fSampai'), elTeks = $('#teksRentang');
  function fmt(v) {
    return new Date(v + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  function segarkanRentang() {
    var d = elDari.value, s = elSampai.value, t = '';
    if (d && s) t = fmt(d) + ' – ' + fmt(s);
    else if (d) t = 'Mulai ' + fmt(d);
    else if (s) t = 'Sampai ' + fmt(s);
    elTeks.textContent = t || 'Pilih Rentang Tanggal';
    elTeks.classList.toggle('kosong', !t);
  }
  [elDari, elSampai].forEach(function (el) {
    el.addEventListener('click', function () { try { el.showPicker(); } catch (e) { /* abaikan */ } });
    el.addEventListener('change', function () {
      if (elDari.value && elSampai.value && elDari.value > elSampai.value) {
        // jaga urutan: sampai tidak boleh sebelum dari
        if (el === elDari) elSampai.value = elDari.value; else elDari.value = elSampai.value;
      }
      segarkanRentang();
      form.submit();
    });
  });
  segarkanRentang();

  /* ---------- Dialog ---------- */
  var dlgDetail = $('#dlgDetail'), dlgEdit = $('#dlgEdit');
  $$('[data-tutup]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });

  function ambil(id) {
    return fetch(urlRapat(id), { headers: { Accept: 'application/json' } }).then(function (r) {
      if (!r.ok) throw new Error('Data rapat tidak dapat dimuat.');
      return r.json();
    });
  }

  /* ----- Lihat detail ----- */
  function lihat(id) {
    ambil(id).then(function (j) {
      teks('#dNama', j.nama_rapat);
      var st = $('#dStatus');
      st.className = 'lencana ' + j.status_warna;
      st.textContent = j.status_label;

      $('#dAgenda').textContent = j.catatan_rapat || '';
      teks('#dTanggal', j.tanggal_teks);
      teks('#dWaktu', j.waktu + (j.waktu_selesai ? ' – ' + j.waktu_selesai : '') + ' WIB');
      teks('#dTempat', j.tempat_rapat);
      teks('#dPengundang', j.pengundang);
      teks('#dKelompok', j.nama_kelompok);
      teks('#dJumlah', j.jumlah + ' orang');

      var okUrl = function (u) { return /^https?:\/\//i.test(u || ''); };
      $('#dLinkBaris').hidden = !okUrl(j.link_rapat);
      if (okUrl(j.link_rapat)) { $('#dLink').href = j.link_rapat; $('#dLink').textContent = j.link_rapat; }
      $('#dLampiranBaris').hidden = !okUrl(j.lampiran_url);
      if (okUrl(j.lampiran_url)) $('#dLampiran').href = j.lampiran_url;

      var ol = $('#dAnggota');
      ol.textContent = '';
      if (!j.anggota.length) {
        var kosong = document.createElement('li');
        kosong.textContent = 'Belum ada peserta.';
        ol.appendChild(kosong);
      }
      j.anggota.forEach(function (a) {
        var li = document.createElement('li');
        li.appendChild(document.createTextNode(a.nama + ' '));
        var s = document.createElement('small');
        s.textContent = '— ' + a.nomor_wa;
        li.appendChild(s);
        ol.appendChild(li);
      });

      dlgDetail.showModal();
    }).catch(function (e) { alert(e.message); });
  }

  /* ----- Ubah rapat ----- */
  var elGalat = $('#eGalat');
  function tampilGalat(pesan) {
    elGalat.textContent = pesan;
    elGalat.hidden = !pesan;
  }

  function ubah(id) {
    ambil(id).then(function (j) {
      if (!j.bisa_ubah) { alert('Rapat dengan status ini tidak dapat diubah.'); return; }
      tampilGalat('');
      $('#eId').value = j.id;
      $('#eNama').value = j.nama_rapat || '';
      $('#eAgenda').value = j.catatan_rapat || '';
      $('#eTanggal').value = j.tanggal;
      $('#eWaktu').value = j.waktu;
      if ($('#eSelesai')) $('#eSelesai').value = j.waktu_selesai || '';
      $('#eTempat').value = j.id_tempat || '';
      dlgEdit.showModal();
      $('#eNama').focus();
    }).catch(function (e) { alert(e.message); });
  }

  $('#formEdit').addEventListener('submit', function (e) {
    e.preventDefault();
    tampilGalat('');
    var tombol = $('#eSimpan');
    tombol.disabled = true;

    var badan = {
      nama_rapat: $('#eNama').value.trim(),
      catatan_rapat: $('#eAgenda').value.trim(),
      tanggal: $('#eTanggal').value,
      waktu: $('#eWaktu').value,
      id_tempat: $('#eTempat').value
    };
    if ($('#eSelesai')) badan.waktu_selesai = $('#eSelesai').value || null;

    fetch(urlRapat($('#eId').value), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(badan)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) {
          var pesan = j.message || 'Terjadi kesalahan.';
          if (j.errors) pesan = Object.values(j.errors)[0][0];
          throw new Error(pesan);
        }
        window.location.reload();
      });
    }).catch(function (er) {
      tampilGalat(er.message);
      tombol.disabled = false;
    });
  });

  /* ---------- Klik tombol di tabel ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-lihat]');
    if (b) { lihat(b.dataset.lihat); return; }
    b = e.target.closest('[data-edit]');
    if (b) { ubah(b.dataset.edit); }
  });
})();