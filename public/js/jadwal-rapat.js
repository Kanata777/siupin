/* Halaman Jadwal Rapat — SIUPIN
   Simpan sebagai: public/js/jadwal-rapat.js (ganti seluruh isi file lama) */
(function () {
  'use strict';

  var J = window.JADWAL || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function urlRapat(id) { return J.urlShow.replace('__ID__', encodeURIComponent(id)); }
  function teks(id, nilai) { $(id).textContent = nilai == null || nilai === '' ? '-' : nilai; }
  function buat(tag, kelas, isi) {
    var e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (isi != null) e.textContent = isi;
    return e;
  }

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

  function ambil(url) {
    return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) {
      if (!r.ok) throw new Error('Data tidak dapat dimuat.');
      return r.json();
    });
  }

  /* ----- Lihat detail ----- */
  function lihat(id) {
    ambil(urlRapat(id)).then(function (j) {
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
  var elDaftar = $('#ePeserta'), elJudul = $('#ePesertaJudul');
  var elCari = $('#eCari'), elHasil = $('#eHasil'), elUnit = $('#eUnit');
  var elKirimBaris = $('#eKirimBaris'), elKirim = $('#eKirimUlang'), elInfo = $('#eInfoKirim');

  var peserta = new Map();   // nomor_wa -> { nomor_wa, nama }
  var awal = new Set();      // nomor_wa peserta sebelum diedit
  var awalJadwal = null;     // jadwal sebelum diedit
  var statusDraft = false;
  var hasilTerakhir = [];
  var timerCari;

  function tampilGalat(pesan) {
    elGalat.textContent = pesan;
    elGalat.hidden = !pesan;
  }

  function nilaiSelesai() { return $('#eSelesai') ? $('#eSelesai').value : ''; }

  function jadwalBerubah() {
    if (!awalJadwal) return false;
    return $('#eTanggal').value !== awalJadwal.tanggal
      || $('#eWaktu').value !== awalJadwal.waktu
      || ($('#eSelesai') && nilaiSelesai() !== (awalJadwal.selesai || ''))
      || String($('#eTempat').value) !== String(awalJadwal.id_tempat || '');
  }

  function segarkanInfo() {
    var ditambah = 0, dihapus = 0;
    peserta.forEach(function (p, wa) { if (!awal.has(wa)) ditambah++; });
    awal.forEach(function (wa) { if (!peserta.has(wa)) dihapus++; });

    var berubah = jadwalBerubah();
    elKirimBaris.hidden = statusDraft || !berubah;

    var t = [];
    if (statusDraft) {
      t.push('Rapat masih berstatus draft, tidak ada pesan WhatsApp yang dikirim.');
    } else {
      if (ditambah) t.push('Undangan WA akan dikirim ke ' + ditambah + ' anggota baru.');
      if (berubah && elKirim.checked) t.push('Pemberitahuan perubahan akan dikirim ke peserta lama.');
      if (berubah && !elKirim.checked) t.push('Peserta lama tidak akan diberi tahu soal perubahan.');
      if (!ditambah && !berubah) t.push('Tidak ada pesan WhatsApp yang akan dikirim.');
    }
    if (dihapus) t.push(dihapus + ' peserta akan dihapus dari rapat.');
    elInfo.textContent = t.join(' ');
  }

  function renderPeserta() {
    elJudul.textContent = 'Peserta (' + peserta.size + ' orang)';
    elDaftar.textContent = '';

    if (!peserta.size) {
      elDaftar.appendChild(buat('li', 'kosong-kecil', 'Belum ada peserta. Tambahkan lewat kolom pencarian di atas.'));
    }

    peserta.forEach(function (p) {
      var li = buat('li');
      var info = buat('span', 'nama-peserta', p.nama + ' ');
      info.appendChild(buat('small', null, '— ' + p.nomor_wa));
      li.appendChild(info);

      if (!awal.has(p.nomor_wa)) li.appendChild(buat('span', 'lencana-baru', 'Baru'));

      var x = buat('button', 'hapus-peserta', '✕');
      x.type = 'button';
      x.title = 'Hapus ' + p.nama;
      x.setAttribute('aria-label', 'Hapus ' + p.nama);
      x.addEventListener('click', function () {
        peserta.delete(p.nomor_wa);
        renderPeserta();
      });
      li.appendChild(x);
      elDaftar.appendChild(li);
    });

    segarkanInfo();
  }

  function tambahPeserta(daftar) {
    daftar.forEach(function (p) {
      var wa = String(p.nomor_wa || '').trim();
      if (wa && !peserta.has(wa)) peserta.set(wa, { nomor_wa: wa, nama: p.nama });
    });
    renderPeserta();
  }

  function renderHasil(data) {
    hasilTerakhir = data;
    elHasil.textContent = '';
    elHasil.hidden = false;
    if (!data.length) {
      elHasil.appendChild(buat('div', 'kosong-kecil', 'Pegawai tidak ditemukan (atau belum punya nomor WA).'));
      return;
    }
    data.forEach(function (p) {
      var sudah = peserta.has(String(p.nomor_wa).trim());
      var b = buat('button', 'hasil-item' + (sudah ? ' sudah' : ''));
      b.type = 'button';
      b.appendChild(buat('b', null, p.nama));
      b.appendChild(buat('small', null, [p.unit, p.jabatan].filter(Boolean).join(' · ')));
      if (sudah) b.appendChild(buat('em', null, '✔'));
      b.addEventListener('click', function () {
        tambahPeserta([p]);
        renderHasil(hasilTerakhir);
      });
      elHasil.appendChild(b);
    });
  }

  elCari.addEventListener('input', function () {
    clearTimeout(timerCari);
    var q = elCari.value.trim();
    if (q.length < 2) { elHasil.hidden = true; return; }
    timerCari = setTimeout(function () {
      ambil(J.urlPegawai + '?q=' + encodeURIComponent(q)).then(renderHasil).catch(function () {});
    }, 250);
  });
  // Enter di kolom cari jangan men-submit form
  elCari.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.kotak-cari-e')) elHasil.hidden = true;
  });

  elUnit.addEventListener('change', function () {
    var unit = elUnit.value;
    if (!unit) return;
    elUnit.disabled = true;
    ambil(J.urlPegawai + '?unit=' + encodeURIComponent(unit)).then(function (daftar) {
      if (!daftar.length) { tampilGalat('Tidak ada pegawai ber-nomor WA pada unit ini.'); return; }
      tampilGalat('');
      tambahPeserta(daftar);
    }).catch(function (e) {
      tampilGalat(e.message);
    }).then(function () {
      elUnit.value = '';
      elUnit.disabled = false;
    });
  });

  ['#eTanggal', '#eWaktu', '#eSelesai', '#eTempat'].forEach(function (s) {
    var el = $(s);
    if (el) { el.addEventListener('change', segarkanInfo); el.addEventListener('input', segarkanInfo); }
  });
  elKirim.addEventListener('change', segarkanInfo);

  dlgEdit.addEventListener('close', function () {
    elCari.value = '';
    elHasil.hidden = true;
  });

  function ubah(id) {
    ambil(urlRapat(id)).then(function (j) {
      if (!j.bisa_ubah) { alert('Rapat dengan status ini tidak dapat diubah.'); return; }
      tampilGalat('');
      $('#eId').value = j.id;
      $('#eNama').value = j.nama_rapat || '';
      $('#eAgenda').value = j.catatan_rapat || '';
      $('#eTanggal').value = j.tanggal;
      $('#eWaktu').value = j.waktu;
      if ($('#eSelesai')) $('#eSelesai').value = j.waktu_selesai || '';
      $('#eTempat').value = j.id_tempat || '';

      statusDraft = j.status_tampil === 'draft';
      awalJadwal = {
        tanggal: j.tanggal, waktu: j.waktu,
        selesai: j.waktu_selesai || '', id_tempat: j.id_tempat || ''
      };

      peserta = new Map();
      awal = new Set();
      (j.anggota || []).forEach(function (a) {
        var wa = String(a.nomor_wa || '').trim();
        peserta.set(wa, { nomor_wa: wa, nama: a.nama });
        awal.add(wa);
      });
      elKirim.checked = true;
      renderPeserta();

      dlgEdit.showModal();
      $('#eNama').focus();
    }).catch(function (e) { alert(e.message); });
  }

  $('#formEdit').addEventListener('submit', function (e) {
    e.preventDefault();
    tampilGalat('');

    if (!peserta.size) {
      tampilGalat('Pilih minimal satu peserta.');
      return;
    }

    var tombol = $('#eSimpan');
    tombol.disabled = true;

    var badan = {
      nama_rapat: $('#eNama').value.trim(),
      catatan_rapat: $('#eAgenda').value.trim(),
      tanggal: $('#eTanggal').value,
      waktu: $('#eWaktu').value,
      id_tempat: $('#eTempat').value,
      peserta: Array.from(peserta.values()).map(function (p) { return { nomor_wa: p.nomor_wa, nama: p.nama }; }),
      kirim_pembaruan: elKirim.checked
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