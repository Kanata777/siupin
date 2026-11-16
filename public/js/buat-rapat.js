/* Halaman Buat Rapat — SIUPIN */
(function () {
  'use strict';

  var K = window.SIUPIN || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  var peserta = new Map();      // nomor_wa -> { nomor_wa, nama, tags:Set }
  var tampilSemua = false;
  var tersimpan = K.tersimpan || [];

  var elForm = $('#formRapat'), elHidden = $('#pesertaJson');
  var elJudul = $('#judulPeserta'), elChip = $('#chipPeserta'), elJumlah = $('#jumlahAnggota');
  var elCari = $('#cariPegawai'), elHasil = $('#hasilCari');
  var elNamaKel = $('#namaKelompok'), btnSimpan = $('#btnSimpanKelompok');
  var elIsiKel = $('#isiKelompok');
  var elTempat = $('#id_tempat'), elTanggal = $('#tanggal'), elWaktu = $('#waktu'), elSelesai = $('#waktu_selesai'), elBadge = $('#badgeTempat');
  var elToast = $('#toast');

  function buat(tag, kelas, teks) {
    var e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (teks != null) e.textContent = teks;
    return e;
  }

  var NS = 'http://www.w3.org/2000/svg';
  function ikon(nama) {
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('class', 'i');
    var use = document.createElementNS(NS, 'use');
    use.setAttribute('href', '#i-' + nama);
    svg.appendChild(use);
    return svg;
  }

  /* ---------- Notifikasi ---------- */
  var toastTimer;
  function notif(pesan, galat) {
    elToast.textContent = pesan;
    elToast.className = 'toast' + (galat ? ' galat' : '');
    elToast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { elToast.hidden = true; }, 3500);
  }

  /* ---------- HTTP ---------- */
  function ambil(url) {
    return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) {
      if (!r.ok) throw new Error('Gagal memuat data.');
      return r.json();
    });
  }
  function kirim(url, metode, body) {
    return fetch(url, {
      method: metode,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) {
          var pesan = j.message || 'Terjadi kesalahan.';
          if (j.errors) pesan = Object.values(j.errors)[0][0];
          throw new Error(pesan);
        }
        return j;
      });
    });
  }

  /* ---------- Peserta terpilih ---------- */
  function tambah(daftar, tag) {
    daftar.forEach(function (p) {
      var e = peserta.get(p.nomor_wa);
      if (!e) { e = { nomor_wa: p.nomor_wa, nama: p.nama, tags: new Set() }; peserta.set(p.nomor_wa, e); }
      e.tags.add(tag);
    });
  }
  function lepas(tag) {
    Array.from(peserta.keys()).forEach(function (wa) {
      var e = peserta.get(wa);
      e.tags.delete(tag);
      if (!e.tags.size) peserta.delete(wa);
    });
  }

  function render() {
    var daftar = Array.from(peserta.values());
    elJudul.textContent = 'Peserta Terpilih (' + daftar.length + ' orang)';
    elJumlah.textContent = daftar.length + ' orang';
    elChip.textContent = '';

    if (!daftar.length) {
      elChip.appendChild(buat('span', 'kosong-kecil', 'Belum ada peserta dipilih.'));
    }

    (tampilSemua ? daftar : daftar.slice(0, 6)).forEach(function (e) {
      var s = buat('span', 'chip-orang');
      s.appendChild(ikon('user'));
      s.appendChild(document.createTextNode(e.nama + ' '));
      var x = buat('button', 'x');
      x.appendChild(ikon('x'));
      x.type = 'button';
      x.setAttribute('aria-label', 'Hapus ' + e.nama);
      x.addEventListener('click', function () { peserta.delete(e.nomor_wa); render(); });
      s.appendChild(x);
      elChip.appendChild(s);
    });

    if (daftar.length > 6) {
      var b = buat('button', 'chip-orang lebih', tampilSemua ? 'Ringkas' : '+' + (daftar.length - 6) + ' lainnya');
      b.type = 'button';
      b.addEventListener('click', function () { tampilSemua = !tampilSemua; render(); });
      elChip.appendChild(b);
    }

    elHidden.value = JSON.stringify(daftar.map(function (e) { return { nomor_wa: e.nomor_wa, nama: e.nama }; }));
  }

  /* ---------- Tab ---------- */
  function tampilTab(nama) {
    $$('.tab-peserta').forEach(function (t) { t.classList.toggle('aktif', t.dataset.tab === nama); });
    $('#tabPilih').hidden = nama !== 'pilih';
    $('#tabKelompok').hidden = nama !== 'kelompok';
  }
  $$('.tab-peserta').forEach(function (t) {
    t.addEventListener('click', function () { tampilTab(t.dataset.tab); });
  });

  /* ---------- Pencarian pegawai ---------- */
  var timerCari;
  var hasilTerakhir = [];

  function renderHasil(data) {
    hasilTerakhir = data;
    elHasil.textContent = '';
    elHasil.hidden = false;
    if (!data.length) { elHasil.appendChild(buat('div', 'kosong-kecil', 'Pegawai tidak ditemukan (atau belum punya nomor WA).')); return; }
    data.forEach(function (p) {
      var sudah = peserta.has(p.nomor_wa);
      var b = buat('button', 'hasil-item' + (sudah ? ' sudah' : ''));
      b.type = 'button';
      b.appendChild(buat('b', null, p.nama));
      b.appendChild(buat('small', null, [p.unit, p.jabatan].filter(Boolean).join(' · ')));
      if (sudah) b.appendChild(buat('em', null, '✔'));
      b.addEventListener('click', function () {
        tambah([p], 'manual');
        render();
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
      ambil(K.urlPegawai + '?q=' + encodeURIComponent(q)).then(renderHasil).catch(function () {});
    }, 250);
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.kotak-cari')) elHasil.hidden = true;
  });

  /* ---------- Akordeon: unit / jabatan / kelompok ---------- */
  function anggotaDari(jenis, nilai) {
    if (jenis === 'kelompok') {
      return ambil(K.urlAnggota.replace('__ID__', encodeURIComponent(nilai))).then(function (j) { return j.anggota; });
    }
    return ambil(K.urlPegawai + '?' + jenis + '=' + encodeURIComponent(nilai));
  }

  function ubahItem(cek) {
    var tag = cek.dataset.jenis + ':' + cek.dataset.nilai;
    if (!cek.checked) { lepas(tag); render(); return Promise.resolve(); }
    cek.disabled = true;
    return anggotaDari(cek.dataset.jenis, cek.dataset.nilai).then(function (daftar) {
      if (!daftar.length) notif('Tidak ada pegawai ber-nomor WA pada pilihan ini.', true);
      tambah(daftar, tag);
    }).catch(function (e) {
      cek.checked = false;
      notif(e.message, true);
    }).then(function () {
      cek.disabled = false;
      render();
    });
  }

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.classList.contains('acc-cek')) { ubahItem(t); return; }
    if (t.classList.contains('acc-semua')) {
      var acc = t.closest('.acc');
      var tugas = $$('.acc-cek', acc).filter(function (c) { return c.checked !== t.checked; });
      tugas.forEach(function (c) { c.checked = t.checked; });
      Promise.all(tugas.map(ubahItem));
    }
  });

  $$('.acc-panah').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var isi = btn.closest('.acc').querySelector('.acc-isi');
      var buka = isi.hidden;
      isi.hidden = !buka;
      btn.setAttribute('aria-expanded', buka ? 'true' : 'false');
      btn.classList.toggle('buka', buka);
    });
  });

  function renderTersimpan() {
    var dipilih = new Set($$('input:checked', elIsiKel).map(function (i) { return i.dataset.nilai; }));
    elIsiKel.textContent = '';
    if (!tersimpan.length) { elIsiKel.appendChild(buat('span', 'kosong-kecil', 'Belum ada kelompok tersimpan.')); return; }
    tersimpan.forEach(function (g) {
      var l = buat('label', 'acc-item');
      var i = document.createElement('input');
      i.type = 'checkbox'; i.className = 'acc-cek';
      i.dataset.jenis = 'kelompok'; i.dataset.nilai = String(g.id);
      i.checked = dipilih.has(String(g.id));
      l.appendChild(i);
      l.appendChild(document.createTextNode(' ' + g.nama + ' (' + g.jumlah + ' orang)'));
      elIsiKel.appendChild(l);
    });
  }

  /* ---------- Kelompok: simpan ---------- */
  function terapkanDaftar(j) {
    tersimpan = j.tersimpan || [];
    renderTersimpan();
  }

  btnSimpan.addEventListener('click', function () {
    var nama = elNamaKel.value.trim();
    if (!nama) { notif('Nama kelompok wajib diisi.', true); return; }
    if (!peserta.size) { notif('Pilih peserta dulu di tab "Pilih Peserta".', true); return; }
    btnSimpan.disabled = true;
    kirim(K.urlSimpan, 'POST', {
      id_kelompok: null,
      nama_kelompok: nama,
      peserta: Array.from(peserta.values()).map(function (e) { return { nomor_wa: e.nomor_wa, nama: e.nama }; })
    }).then(function (j) {
      terapkanDaftar(j);
      elNamaKel.value = '';
      notif(j.pesan);
    }).catch(function (e) { notif(e.message, true); })
      .then(function () { btnSimpan.disabled = false; });
  });

  /* ---------- Metode rapat ---------- */
  function aturMetode() {
    var daring = ($('input[name="metode"]:checked') || {}).value === 'daring';
    $('#barisLink').hidden = !daring;
  }
  $$('input[name="metode"]').forEach(function (r) { r.addEventListener('change', aturMetode); });

  /* ---------- Ketersediaan tempat ---------- */
  function cekTempat() {
    elBadge.className = 'badge-tempat';
    elBadge.textContent = '';
    if (!elTempat.value || !elTanggal.value || !elWaktu.value) return;
    var url = K.urlCek + '?id_tempat=' + encodeURIComponent(elTempat.value)
            + '&tanggal=' + encodeURIComponent(elTanggal.value)
            + '&waktu=' + encodeURIComponent(elWaktu.value)
            + (elSelesai && elSelesai.value ? '&waktu_selesai=' + encodeURIComponent(elSelesai.value) : '');
    ambil(url).then(function (j) {
      if (j.tersedia === null || j.tersedia === undefined) return;
      var ok = j.tersedia === true;
      elBadge.className = 'badge-tempat ' + (ok ? 'ok' : 'bentrok');
      elBadge.textContent = '';
      elBadge.appendChild(ikon(ok ? 'cek' : 'silang'));
      elBadge.appendChild(document.createTextNode(ok ? 'Tersedia' : 'Sudah dipakai'));
    }).catch(function () {});
  }
  [elTempat, elTanggal, elWaktu, elSelesai].forEach(function (el) {
    if (el) el.addEventListener('change', cekTempat);
  });

  /* ---------- Tampilan tanggal & waktu (diformat seperti desain) ---------- */
  var elTeksTanggal = $('#teksTanggal'), elTeksWaktu = $('#teksWaktu');
  function segarkanTampilan() {
    if (elTanggal.value) {
      var d = new Date(elTanggal.value + 'T00:00:00');
      elTeksTanggal.textContent = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    } else {
      elTeksTanggal.textContent = 'Pilih tanggal';
    }
    var mulai = elWaktu.value || '--:--';
    elTeksWaktu.textContent = mulai + (elSelesai ? ' – ' + (elSelesai.value || '--:--') : '') + ' WIB';
  }
  [elTanggal, elWaktu, elSelesai].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', segarkanTampilan);
    el.addEventListener('change', segarkanTampilan);
    el.addEventListener('click', function () { try { el.showPicker(); } catch (e) { /* abaikan */ } });
  });

  var elFile = $('#lampiran'), elNamaFile = $('#namaFile');
  if (elFile) {
    elFile.addEventListener('change', function () {
      elNamaFile.textContent = elFile.files.length ? elFile.files[0].name : 'Pilih File';
    });
  }

  /* ---------- Status pengiriman WA ---------- */
  var elPilihRapat = $('#pilihRapatWa'), elFilter = $('#filterStatusWa');
  var elTbodyWa = $('#tbodyStatusWa'), btnUlangSemua = $('#btnUlangSemua');
  var timerStatus;

  function muatStatus() {
    clearTimeout(timerStatus);
    if (!elPilihRapat || !elPilihRapat.value) return;

    var url = K.urlStatus.replace('__ID__', encodeURIComponent(elPilihRapat.value))
            + '?status=' + encodeURIComponent(elFilter.value);

    ambil(url).then(function (j) {
      elTbodyWa.innerHTML = j.html;      // HTML dari server (sudah di-escape oleh Blade)
      ['total', 'terkirim', 'pending', 'gagal'].forEach(function (k) {
        $('#wa-' + k).textContent = j.ringkasan[k];
      });
      btnUlangSemua.hidden = !j.ringkasan.gagal;
      // selama masih ada yang pending, segarkan otomatis tiap 5 detik
      if (j.ringkasan.pending > 0) timerStatus = setTimeout(muatStatus, 5000);
    }).catch(function () {
      timerStatus = setTimeout(muatStatus, 10000);
    });
  }

  function kirimUlang(body) {
    var url = K.urlUlang.replace('__ID__', encodeURIComponent(elPilihRapat.value));
    return kirim(url, 'POST', body || {}).then(function (j) {
      notif(j.pesan);
      muatStatus();
    }).catch(function (e) { notif(e.message, true); });
  }

  if (elPilihRapat) {
    elPilihRapat.addEventListener('change', muatStatus);
    elFilter.addEventListener('change', muatStatus);
    btnUlangSemua.addEventListener('click', function () { kirimUlang({}); });
    elTbodyWa.addEventListener('click', function (e) {
      var b = e.target.closest('[data-aksi="ulang"]');
      if (b) kirimUlang({ id_undangan: parseInt(b.dataset.id, 10) });
    });
  }

  /* ---------- Submit & reset ---------- */
  elForm.addEventListener('submit', function (e) {
    if (!peserta.size) {
      e.preventDefault();
      notif('Pilih minimal satu peserta.', true);
      elCari.focus();
    }
  });
  elForm.addEventListener('reset', function () {
    setTimeout(function () {
      peserta.clear();
      $$('.acc-cek, .acc-semua').forEach(function (c) { c.checked = false; });
      elNamaKel.value = '';
      render(); aturMetode(); cekTempat(); segarkanTampilan();
      if (elNamaFile) elNamaFile.textContent = 'Pilih File';
    }, 0);
  });

  /* ---------- Awal ---------- */
  (K.pesertaLama || []).forEach(function (p) { tambah([{ nomor_wa: p.nomor_wa, nama: p.nama }], 'manual'); });
  renderTersimpan();
  render();
  aturMetode();
  cekTempat();
  segarkanTampilan();
  muatStatus();
  if (K.pesanSukses) notif(K.pesanSukses);
})();