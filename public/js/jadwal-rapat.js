/* Halaman Jadwal Rapat — SIUPIN
   Simpan sebagai: public/js/jadwal-rapat.js (ganti seluruh isi file lama)

   Dialog Edit Rapat:
   - tombol simpan biru  : tidak ada peserta baru (tidak ada undangan WA baru)
   - tombol simpan hijau : ada peserta baru yang akan menerima undangan WA
   - rapat draft         : selalu biru (draft tidak pernah mengirim WA)
   - tambah dari unit A lalu unit B: peserta DITAMBAH, tidak ditimpa
   - daftar peserta dikelompokkan per unit; kelompok/peserta BARU tampil paling atas
   - "Hapus semua", "Hapus unit" di tiap kelompok, dan dropdown "Hapus semua pegawai dari unit..."
   - bila >= 2 unit ditambahkan lewat dropdown unit, server membuat kelompok tersendiri tiap unit
*/
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

  /* CSS tambahan untuk judul kelompok unit (tidak perlu mengubah file CSS/blade) */
  (function () {
    var st = document.createElement('style');
    st.textContent =
      '.daftar-edit-peserta .judul-unit{display:flex;justify-content:space-between;align-items:center;gap:8px;' +
      'background:#eef2f7;font-weight:600;font-size:.82rem;padding:6px 10px;position:sticky;top:0;z-index:1;list-style:none}' +
      '.daftar-edit-peserta .judul-unit .hapus-unit{background:#fff;color:#c62828;border:1px solid #c62828;' +
      'border-radius:6px;padding:1px 8px;font-size:.75rem;cursor:pointer}' +
      '.daftar-edit-peserta .judul-unit .hapus-unit:hover{background:#fdecec}';
    document.head.appendChild(st);
  })();

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
        s.textContent = '— ' + a.nomor_wa + (a.unit ? ' · ' + a.unit : '');
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
  var elHapusSemua = $('#eHapusSemua'), elHapusUnit = $('#eHapusUnit');
  var elKirimBaris = $('#eKirimBaris'), elKirim = $('#eKirimUlang'), elInfo = $('#eInfoKirim');
  var elSimpan = $('#eSimpan');

  var peserta = new Map();      // nomor_wa -> { nomor_wa, nama, unit }
  var awal = new Set();         // nomor_wa peserta sebelum diedit
  var unitAnggota = new Map();  // unit -> [nomor_wa] yang ditambahkan lewat dropdown unit (sesi edit ini)
  var awalJadwal = null;        // jadwal sebelum diedit
  var statusDraft = false;
  var hasilTerakhir = [];
  var timerCari;

  var TANPA_UNIT = '__tanpa_unit__';
  var LABEL_SIMPAN = 'Simpan Perubahan';
  var LABEL_SIMPAN_WA = 'Simpan Perubahan & Kirim ke WA';
  var LABEL_TAMBAH_UNIT = elUnit.options[0] ? elUnit.options[0].textContent : '+ Tambah semua pegawai dari unit...';

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

  /** Tombol simpan: hijau + "& Kirim ke WA" bila ada peserta baru yang akan dikirimi undangan */
  function aturTombolSimpan(kirimWa) {
    elSimpan.classList.toggle('btn-hijau', kirimWa);
    elSimpan.classList.toggle('btn-biru', !kirimWa);
    elSimpan.textContent = kirimWa ? LABEL_SIMPAN_WA : LABEL_SIMPAN;
    elSimpan.title = kirimWa ? 'Undangan WhatsApp akan dikirim ke peserta baru' : '';
  }

  function segarkanInfo(catatan) {
    var ditambah = 0, dihapus = 0;
    peserta.forEach(function (p, wa) { if (!awal.has(wa)) ditambah++; });
    awal.forEach(function (wa) { if (!peserta.has(wa)) dihapus++; });

    var berubah = jadwalBerubah();
    elKirimBaris.hidden = statusDraft || !berubah;

    aturTombolSimpan(!statusDraft && ditambah > 0);

    var t = [];
    if (typeof catatan === 'string' && catatan) t.push(catatan);
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

  /** Isi ulang dropdown "Hapus dari unit" sesuai peserta saat ini; juga tampil/sembunyikan "Hapus semua" */
  function segarkanHapusUnit() {
    var hitung = new Map();
    peserta.forEach(function (p) {
      var u = p.unit || '';
      hitung.set(u, (hitung.get(u) || 0) + 1);
    });

    elHapusUnit.textContent = '';
    elHapusUnit.appendChild(new Option('− Hapus semua pegawai dari unit...', ''));
    hitung.forEach(function (n, u) {
      elHapusUnit.appendChild(new Option((u || '(Tanpa unit)') + ' (' + n + ')', u === '' ? TANPA_UNIT : u));
    });
    elHapusUnit.value = '';
    elHapusUnit.hidden = hitung.size === 0;
    elHapusSemua.hidden = peserta.size === 0;
  }

  /** Hapus semua peserta milik satu unit ('' = tanpa unit), dengan konfirmasi */
  function hapusDariUnit(unit) {
    var hapus = [];
    peserta.forEach(function (p, wa) { if ((p.unit || '') === unit) hapus.push(wa); });

    if (hapus.length && confirm('Hapus ' + hapus.length + ' peserta dari unit "' + (unit || 'tanpa unit') + '"?')) {
      hapus.forEach(function (wa) { peserta.delete(wa); });
      unitAnggota.delete(unit);
    }
    renderPeserta();
  }

  /**
   * Gambar daftar peserta, dikelompokkan per unit.
   * Kelompok yang berisi peserta baru tampil paling atas, dan peserta baru di atas peserta lama
   * dalam kelompoknya, supaya hasil klik langsung terlihat tanpa menggulir.
   * $gulirAtas = true -> daftar digulir ke atas.
   */
  function renderPeserta(gulirAtas, catatan) {
    elJudul.textContent = 'Peserta (' + peserta.size + ' orang)';
    elDaftar.textContent = '';

    if (!peserta.size) {
      elDaftar.appendChild(buat('li', 'kosong-kecil', 'Belum ada peserta. Tambahkan lewat kolom pencarian di atas.'));
    }

    var grup = new Map();
    peserta.forEach(function (p) {
      var u = p.unit || '';
      if (!grup.has(u)) grup.set(u, []);
      grup.get(u).push(p);
    });

    var baru = function (p) { return awal.has(p.nomor_wa) ? 0 : 1; };
    var daftarGrup = Array.from(grup.entries());
    daftarGrup.forEach(function (g) {
      g[1] = g[1].slice().sort(function (a, b) { return baru(b) - baru(a); }); // stabil: urutan asal tetap
    });
    daftarGrup.sort(function (a, b) {
      var ba = a[1].some(function (p) { return baru(p); }) ? 1 : 0;
      var bb = b[1].some(function (p) { return baru(p); }) ? 1 : 0;
      return bb - ba;
    });

    daftarGrup.forEach(function (g) {
      var unit = g[0], anggota = g[1];

      var kepala = buat('li', 'judul-unit');
      kepala.appendChild(buat('span', null, (unit || '(Tanpa unit)') + ' · ' + anggota.length + ' orang'));
      var hu = buat('button', 'hapus-unit', 'Hapus unit');
      hu.type = 'button';
      hu.title = 'Hapus semua peserta unit ini';
      hu.addEventListener('click', function () { hapusDariUnit(unit); });
      kepala.appendChild(hu);
      elDaftar.appendChild(kepala);

      anggota.forEach(function (p) {
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
    });

    if (gulirAtas) elDaftar.scrollTop = 0;

    segarkanHapusUnit();
    segarkanInfo(catatan);
  }

  /** Menambah peserta (tidak pernah menimpa yang sudah ada). $unit = unit asal bila dari dropdown unit */
  function tambahPeserta(daftar, unit) {
    var ditambah = 0;
    daftar.forEach(function (p) {
      var wa = String(p.nomor_wa || '').trim();
      if (!wa) return;
      if (!peserta.has(wa)) {
        peserta.set(wa, { nomor_wa: wa, nama: p.nama, unit: p.unit || unit || '' });
        ditambah++;
      } else if (!peserta.get(wa).unit && (p.unit || unit)) {
        peserta.get(wa).unit = p.unit || unit;
      }
    });

    var catatan = '';
    if (unit) {
      catatan = ditambah
        ? ditambah + ' pegawai dari unit "' + unit + '" ditambahkan.'
        : 'Semua pegawai unit "' + unit + '" sudah ada di daftar.';
    }
    renderPeserta(true, catatan);
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

  /* Tambah semua pegawai dari satu unit (menambah ke peserta yang sudah ada) */
  elUnit.addEventListener('change', function () {
    var unit = elUnit.value;
    if (!unit) return;
    elUnit.disabled = true;
    elUnit.options[0].textContent = 'Memuat pegawai ' + unit + '...';
    elUnit.value = '';

    ambil(J.urlPegawai + '?unit=' + encodeURIComponent(unit)).then(function (daftar) {
      if (!daftar.length) { tampilGalat('Tidak ada pegawai ber-nomor WA pada unit ini.'); return; }
      tampilGalat('');

      var lama = unitAnggota.get(unit) || [];
      var baru = daftar.map(function (p) { return String(p.nomor_wa).trim(); })
        .filter(function (w) { return w && lama.indexOf(w) < 0; });
      unitAnggota.set(unit, lama.concat(baru));

      tambahPeserta(daftar, unit);
    }).catch(function (e) {
      tampilGalat(e.message);
    }).then(function () {
      elUnit.options[0].textContent = LABEL_TAMBAH_UNIT;
      elUnit.value = '';
      elUnit.disabled = false;
    });
  });

  /* Hapus semua peserta */
  elHapusSemua.addEventListener('click', function () {
    if (!peserta.size) return;
    if (!confirm('Hapus semua ' + peserta.size + ' peserta dari daftar?')) return;
    peserta = new Map();
    unitAnggota = new Map();
    renderPeserta();
  });

  /* Hapus semua peserta dari satu unit (dropdown) */
  elHapusUnit.addEventListener('change', function () {
    var v = elHapusUnit.value;
    if (!v) return;
    hapusDariUnit(v === TANPA_UNIT ? '' : v);
  });

  ['#eTanggal', '#eWaktu', '#eSelesai', '#eTempat'].forEach(function (s) {
    var el = $(s);
    if (el) {
      el.addEventListener('change', function () { segarkanInfo(); });
      el.addEventListener('input', function () { segarkanInfo(); });
    }
  });
  elKirim.addEventListener('change', function () { segarkanInfo(); });

  dlgEdit.addEventListener('close', function () {
    elCari.value = '';
    elHasil.hidden = true;
    unitAnggota = new Map();
    aturTombolSimpan(false);
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
      unitAnggota = new Map();
      (j.anggota || []).forEach(function (a) {
        var wa = String(a.nomor_wa || '').trim();
        peserta.set(wa, { nomor_wa: wa, nama: a.nama, unit: a.unit || '' });
        awal.add(wa);
      });
      elKirim.checked = true;
      elSimpan.disabled = false;
      renderPeserta(true);

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

    var tombol = elSimpan;
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

    // Kelompok tersendiri per unit: hanya bila >= 2 unit ditambahkan lewat dropdown unit
    var kelompokUnit = [];
    unitAnggota.forEach(function (daftarWa, unit) {
      var isi = daftarWa
        .filter(function (wa) { return peserta.has(wa); })
        .map(function (wa) { return { nomor_wa: wa, nama: peserta.get(wa).nama }; });
      if (isi.length) kelompokUnit.push({ unit: unit, peserta: isi });
    });
    if (kelompokUnit.length >= 2) badan.kelompok_unit = kelompokUnit;

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