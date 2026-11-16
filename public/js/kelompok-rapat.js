/* Halaman Kelompok Rapat — SIUPIN
   Dua halaman setara (tab): Daftar Kelompok  <->  Daftar Anggota. */
(function () {
  'use strict';

  var K = window.KELOMPOK || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  var kini = null;                                  // id kelompok yang sedang menjadi filter (null = semua)
  var hal  = K.hal === 'anggota' ? 'anggota' : 'daftar';   // halaman yang sedang tampil
  var semua = [];                                   // seluruh anggota dari semua kelompok

  /* ---------- Util ---------- */
  var NS = 'http://www.w3.org/2000/svg';
  function ikon(nama) {
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('class', 'i');
    var use = document.createElementNS(NS, 'use');
    use.setAttribute('href', '#i-' + nama);
    svg.appendChild(use);
    return svg;
  }
  function buat(tag, kelas, teks) {
    var e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (teks != null) e.textContent = teks;
    return e;
  }
  var PALET = [['#dbe9fb', '#1a5fd0'], ['#dff3e3', '#1f8f3d'], ['#fdf0cf', '#8a5a00'],
               ['#fde6cf', '#a85a00'], ['#ece3fb', '#6a3fc4'], ['#fde0df', '#c62828']];
  /* lingkaran berisi inisial nama, warnanya tetap untuk nama yang sama */
  function avatar(nama) {
    nama = String(nama || '?').trim() || '?';
    var kata = nama.replace(/[.,]/g, ' ').split(/\s+/).filter(Boolean);
    var ini = (kata[0].charAt(0) + (kata.length > 1 ? kata[1].charAt(0) : '')).toUpperCase();
    var h = 0;
    for (var i = 0; i < nama.length; i++) h = (h * 31 + nama.charCodeAt(i)) >>> 0;
    var w = PALET[h % PALET.length];
    var s = buat('span', 'avatar-ang', ini);
    s.style.background = w[0];
    s.style.color = w[1];
    return s;
  }
  function url(tpl, id, detail) {
    return tpl.replace('__ID__', encodeURIComponent(id)).replace('__D__', encodeURIComponent(detail == null ? '' : detail));
  }

  var elToast = $('#toast'), toastTimer;
  function notif(pesan, galat) {
    elToast.textContent = pesan;
    elToast.className = 'toast' + (galat ? ' galat' : '');
    elToast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { elToast.hidden = true; }, 3500);
  }
  // pesan yang ditampilkan setelah halaman dimuat ulang
  function notifLaluMuat(pesan) { try { sessionStorage.setItem('kelToast', pesan); } catch (e) { /* abaikan */ } }

  function api(alamat, metode, badan) {
    return fetch(alamat, {
      method: metode || 'GET',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: badan ? JSON.stringify(badan) : undefined
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

  function galat(elemen, pesan) { elemen.textContent = pesan || ''; elemen.hidden = !pesan; }

  $$('[data-tutup]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });

  /* =====================================================================
     PERPINDAHAN HALAMAN (tab) + URL
     ===================================================================== */
  var elGrid = $('.kel-grid');
  var tabs = $$('.kel-tab a');

  // simpan halaman & filter di URL (tanpa memuat ulang) dan perbarui tautan tab
  function setUrl() {
    try {
      var p = new URLSearchParams(window.location.search);
      if (kini) p.set('pilih', kini); else p.delete('pilih');
      if (hal === 'anggota') p.set('hal', 'anggota'); else p.delete('hal');
      var qs = p.toString();
      window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));

      tabs.forEach(function (a) {
        var q = new URLSearchParams(p.toString());
        if (a.dataset.hal === 'anggota') q.set('hal', 'anggota'); else q.delete('hal');
        var s = q.toString();
        a.href = K.urlIndex + (s ? '?' + s : '');
      });
    } catch (e) { /* abaikan */ }
  }

  function tampilHal(h) {
    hal = h === 'anggota' ? 'anggota' : 'daftar';
    elGrid.classList.toggle('mode-daftar', hal === 'daftar');
    elGrid.classList.toggle('mode-anggota', hal === 'anggota');
    tabs.forEach(function (a) { a.classList.toggle('aktif', a.dataset.hal === hal); });
    setUrl();
  }

  tabs.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      tampilHal(a.dataset.hal);
    });
  });

  /* =====================================================================
     HALAMAN DAFTAR ANGGOTA (semua kelompok + filter kelompok)
     ===================================================================== */
  var tbKel = $('#tbKelompok');
  var elCariAng = $('#cariAnggota'), tbAng = $('#tbAnggota'), elFKel = $('#fKelompok');

  function tandaiBaris(id) {
    $$('tr[data-id]', tbKel).forEach(function (tr) {
      tr.classList.toggle('dipilih', !!id && tr.dataset.id === String(id));
    });
  }

  // jumlah anggota di tabel kelompok & di tab mengikuti data terbaru
  function perbaruiJumlah() {
    var c = {};
    semua.forEach(function (a) { c[a.id_kelompok] = (c[a.id_kelompok] || 0) + 1; });
    $$('tr[data-id]', tbKel).forEach(function (tr) {
      var n = $('.n', tr);
      if (n) n.textContent = c[tr.dataset.id] || 0;
    });
    $('#tabJmlAng').textContent = semua.length;
  }

  function muatSemua() {
    return api(K.urlAnggotaSemua).then(function (j) {
      semua = j;
      perbaruiJumlah();
      renderAnggota();
      return j;
    });
  }

  // tombol tambah hanya aktif bila satu kelompok dipilih
  function aturTombol() {
    $('#btnManual').disabled = !kini;
    $('#btnPegawai').disabled = !kini;
  }

  function terapkanFilter() {
    kini = elFKel.value || null;
    aturTombol();
    tandaiBaris(kini);
    setUrl();
    renderAnggota();
  }

  function pilihKelompok(id) {
    elFKel.value = id ? String(id) : '';
    terapkanFilter();
  }

  function renderAnggota() {
    tbAng.textContent = '';

    var kata = elCariAng.value.trim().toLowerCase();
    var daftar = semua.filter(function (a) {
      if (kini && String(a.id_kelompok) !== String(kini)) return false;
      if (!kata) return true;
      return (a.nama + ' ' + a.unit + ' ' + a.jabatan + ' ' + a.nomor_wa + ' ' + a.kelompok).toLowerCase().indexOf(kata) !== -1;
    });

    $('#aJumlah').textContent = '(' + daftar.length + ' Orang)';

    if (!daftar.length) {
      var tr0 = buat('tr'), td0 = buat('td', 'kosong');
      td0.colSpan = 5;
      td0.appendChild(ikon('users'));
      td0.appendChild(buat('span', null,
        kata ? 'Tidak ada anggota yang cocok.'
             : (kini ? 'Kelompok ini belum punya anggota.' : 'Belum ada anggota di database.')));
      tr0.appendChild(td0);
      tbAng.appendChild(tr0);
      return;
    }

    daftar.forEach(function (a, i) {
      var tr = buat('tr');
      tr.appendChild(buat('td', 'no', String(i + 1)));

      // nama + nomor WA
      var tdNama = buat('td');
      var sel = buat('div', 'sel-ang');
      sel.appendChild(avatar(a.nama));
      var tk = buat('div', 'teks-ang');
      tk.appendChild(buat('b', null, a.nama));
      tk.appendChild(buat('small', null, a.nomor_wa));
      sel.appendChild(tk);
      tdNama.appendChild(sel);
      tdNama.title = a.nama + ' — ' + a.nomor_wa;
      tr.appendChild(tdNama);

      // unit (utama) + jabatan (kecil)
      var tdUnit = buat('td');
      var box = buat('div', 'teks-ang');
      box.appendChild(buat('span', 'unit-ang', a.unit || a.jabatan || '-'));
      if (a.unit && a.jabatan) box.appendChild(buat('small', null, a.jabatan));
      tdUnit.appendChild(box);
      tdUnit.title = [a.unit, a.jabatan].filter(Boolean).join(' · ');
      tr.appendChild(tdUnit);

      // kelompok
      var tdKel = buat('td');
      tdKel.appendChild(buat('span', 'chip-kel', a.kelompok));
      tdKel.title = a.kelompok;
      tr.appendChild(tdKel);

      var tdAksi = buat('td', 'c');
      var b = buat('button', 'ikon-btn merah kecil');
      b.type = 'button';
      b.title = 'Keluarkan dari kelompok';
      b.setAttribute('aria-label', 'Keluarkan ' + a.nama + ' dari ' + a.kelompok);
      b.dataset.hapusAnggota = a.id;
      b.dataset.kelompok = a.id_kelompok;
      b.appendChild(ikon('sampah'));
      tdAksi.appendChild(b);
      tr.appendChild(tdAksi);

      tbAng.appendChild(tr);
    });
  }
  elCariAng.addEventListener('input', renderAnggota);
  elFKel.addEventListener('change', terapkanFilter);

  tbAng.addEventListener('click', function (e) {
    var b = e.target.closest('[data-hapus-anggota]');
    if (!b) return;
    if (!confirm('Keluarkan anggota ini dari kelompok?')) return;
    b.disabled = true;
    api(url(K.urlHapusAnggota, b.dataset.kelompok, b.dataset.hapusAnggota), 'DELETE')
      .then(function (j) { notif(j.pesan); return muatSemua(); })
      .catch(function (er) { notif(er.message, true); b.disabled = false; });
  });

  /* =====================================================================
     HALAMAN DAFTAR KELOMPOK
     ===================================================================== */
  tbKel.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    var aksi = e.target.closest('[data-aksi]');
    var id = tr.dataset.id;

    if (aksi && aksi.dataset.aksi === 'edit') {
      bukaDialogKelompok({ id: id, nama: tr.dataset.nama, ket: tr.dataset.ket });
    } else if (aksi && aksi.dataset.aksi === 'hapus') {
      hapusKelompok(id, tr.dataset.nama);
    } else if (aksi && aksi.dataset.aksi === 'lihat') {
      bukaDetail(id);
    } else {
      // klik baris = pindah ke halaman Daftar Anggota, disaring ke kelompok ini
      pilihKelompok(id);
      tampilHal('anggota');
    }
  });

  /* ---------- Simpan kelompok ---------- */
  function kirimKelompok(id, badan) {
    var req = id ? api(url(K.urlUbah, id), 'PUT', badan) : api(K.urlBuat, 'POST', badan);
    return req.then(function (j) {
      notifLaluMuat(j.pesan);
      var p = new URLSearchParams(window.location.search);
      p.set('pilih', j.id);
      p.delete('page');
      p.delete('hal');          // setelah simpan kembali ke halaman Daftar Kelompok
      window.location.href = K.urlIndex + '?' + p.toString();
    });
  }

  /* ---------- Buat / ubah kelompok (dialog) ---------- */
  var dlgKel = $('#dlgKelompok'), elKGalat = $('#kGalat');

  function bukaDialogKelompok(k) {
    galat(elKGalat, '');
    $('#kSimpan').disabled = false;
    $('#kId').value = k && k.id ? k.id : '';
    $('#kJudul').textContent = k && k.id ? 'Edit Kelompok' : 'Buat Kelompok Baru';
    $('#kNama').value = k && k.nama ? k.nama : '';
    if ($('#kKet')) $('#kKet').value = k && k.ket ? k.ket : '';
    dlgKel.showModal();
    $('#kNama').focus();
  }

  $('#btnBuat').addEventListener('click', function () { bukaDialogKelompok(null); });

  $('#formKelompok').addEventListener('submit', function (e) {
    e.preventDefault();
    galat(elKGalat, '');
    var id = $('#kId').value;
    var badan = { nama_kelompok: $('#kNama').value.trim() };
    if ($('#kKet')) badan.keterangan = $('#kKet').value.trim();

    var tombol = $('#kSimpan');
    tombol.disabled = true;

    kirimKelompok(id, badan).catch(function (er) {
      galat(elKGalat, er.message);
      tombol.disabled = false;
    });
  });

  /* ---------- Dialog detail kelompok ---------- */
  var dlgDet = $('#dlgDetail'), detData = null;

  function isiInfo(selektor, nilai) {
    var el = $(selektor);
    if (el) el.textContent = nilai == null || nilai === '' ? '-' : nilai;
  }

  function bukaDetail(id) {
    api(url(K.urlDetail, id)).then(function (j) {
      detData = j;
      $('#dNamaKel').textContent = j.nama;
      $('#dJumlahKel').textContent = j.jumlah + ' Anggota';
      isiInfo('#dUnit', j.unit);
      $('#dUnit').title = j.unit_semua || '';
      isiInfo('#dOleh', j.dibuat_oleh);
      isiInfo('#dTgl', j.dibuat_pada);
      isiInfo('#dKet', j.keterangan);
      dlgDet.showModal();
    }).catch(function (er) { notif(er.message, true); });
  }

  $('#dEdit').addEventListener('click', function () {
    if (!detData) return;
    dlgDet.close();
    bukaDialogKelompok({ id: detData.id, nama: detData.nama, ket: detData.keterangan });
  });
  $('#dHapus').addEventListener('click', function () {
    if (!detData) return;
    dlgDet.close();
    hapusKelompok(detData.id, detData.nama);
  });

  /* ---------- Hapus kelompok ---------- */
  function hapusKelompok(id, nama) {
    if (!confirm('Hapus kelompok "' + nama + '" beserta seluruh anggotanya?')) return;
    api(url(K.urlHapus, id), 'DELETE').then(function (j) {
      notifLaluMuat(j.pesan || 'Kelompok dihapus.');
      var p = new URLSearchParams(window.location.search);
      p.delete('pilih');
      p.delete('hal');
      var qs = p.toString();
      window.location.href = K.urlIndex + (qs ? '?' + qs : '');
    }).catch(function (er) { notif(er.message, true); });
  }

  /* =====================================================================
     TAMBAH ANGGOTA (ke kelompok yang sedang dipilih di filter)
     ===================================================================== */
  var dlgManual = $('#dlgManual'), elMGalat = $('#mGalat');
  $('#btnManual').addEventListener('click', function () {
    if (!kini) return;
    galat(elMGalat, '');
    $('#mNama').value = '';
    $('#mWa').value = '';
    dlgManual.showModal();
    $('#mNama').focus();
  });

  $('#formManual').addEventListener('submit', function (e) {
    e.preventDefault();
    galat(elMGalat, '');
    var nama = $('#mNama').value.trim(), wa = $('#mWa').value.trim();
    if (!nama || !wa) { galat(elMGalat, 'Nama dan nomor WhatsApp wajib diisi.'); return; }

    var tombol = $('#mSimpan');
    tombol.disabled = true;
    api(url(K.urlTambah, kini), 'POST', { manual: true, peserta: [{ nama: nama, nomor_wa: wa }] })
      .then(function (j) { dlgManual.close(); notif(j.pesan); return muatSemua(); })
      .catch(function (er) { galat(elMGalat, er.message); })
      .then(function () { tombol.disabled = false; });
  });

  /* ---------- Tambah dari data pegawai ---------- */
  var dlgPeg = $('#dlgPegawai'), elPCari = $('#pCari'), elPUnit = $('#pUnit'),
      elPHasil = $('#pHasil'), elPSemua = $('#pSemua'), elPTambah = $('#pTambah'), elPInfo = $('#pInfo');
  var hasilPeg = [];            // hasil pencarian terakhir
  var terpilih = new Set();     // nomor_wa yang dicentang
  var timerPeg;

  // nomor WA yang sudah menjadi anggota kelompok yang sedang dipilih
  function nomorAnggota() {
    var s = new Set();
    semua.forEach(function (a) {
      if (String(a.id_kelompok) === String(kini)) s.add(String(a.nomor_wa).trim());
    });
    return s;
  }

  function perbaruiInfo() {
    elPTambah.disabled = terpilih.size === 0;
    elPTambah.textContent = terpilih.size ? 'Tambahkan (' + terpilih.size + ')' : 'Tambahkan';
    elPInfo.textContent = hasilPeg.length ? '· ' + terpilih.size + ' dipilih dari ' + hasilPeg.length + ' hasil' : '';
  }

  function renderPeg(pesanKosong) {
    elPHasil.textContent = '';
    var sudah = nomorAnggota();

    if (!hasilPeg.length) {
      elPHasil.appendChild(buat('div', 'peg-kosong', pesanKosong || 'Ketik nama pegawai atau pilih unit.'));
      elPSemua.checked = false;
      elPSemua.disabled = true;
      perbaruiInfo();
      return;
    }
    elPSemua.disabled = false;

    hasilPeg.forEach(function (p) {
      var ada = sudah.has(p.nomor_wa);
      var l = buat('label', 'peg-item' + (ada ? ' sudah' : ''));
      var cek = document.createElement('input');
      cek.type = 'checkbox';
      cek.disabled = ada;
      cek.checked = !ada && terpilih.has(p.nomor_wa);
      cek.addEventListener('change', function () {
        if (cek.checked) terpilih.add(p.nomor_wa); else terpilih.delete(p.nomor_wa);
        perbaruiInfo();
      });
      l.appendChild(cek);
      l.appendChild(avatar(p.nama));

      var kotak = buat('div', 'teks-ang');
      kotak.appendChild(buat('b', null, p.nama));
      kotak.appendChild(buat('small', null, [ada ? 'Sudah anggota' : null, p.unit, p.jabatan].filter(Boolean).join(' · ')));
      l.appendChild(kotak);
      elPHasil.appendChild(l);
    });
    perbaruiInfo();
  }

  function cariPegawai() {
    var q = elPCari.value.trim(), unit = elPUnit.value;
    if (q.length < 2 && !unit) { hasilPeg = []; renderPeg(); return; }

    var alamat = K.urlPegawai + (q.length >= 2 ? '?q=' + encodeURIComponent(q) : '?unit=' + encodeURIComponent(unit));
    api(alamat).then(function (daftar) {
      // bila pencarian teks + unit dipilih, saring juga menurut unit
      hasilPeg = unit && q.length >= 2 ? daftar.filter(function (p) { return p.unit === unit; }) : daftar;
      renderPeg('Pegawai tidak ditemukan (atau belum punya nomor WA).');
    }).catch(function (er) { hasilPeg = []; renderPeg(er.message); });
  }

  $('#btnPegawai').addEventListener('click', function () {
    if (!kini) return;
    terpilih.clear();
    hasilPeg = [];
    elPCari.value = '';
    elPUnit.value = '';
    elPSemua.checked = false;
    renderPeg();
    dlgPeg.showModal();
    elPCari.focus();
  });

  elPCari.addEventListener('input', function () { clearTimeout(timerPeg); timerPeg = setTimeout(cariPegawai, 250); });
  elPUnit.addEventListener('change', cariPegawai);

  elPSemua.addEventListener('change', function () {
    var sudah = nomorAnggota();
    hasilPeg.forEach(function (p) {
      if (sudah.has(p.nomor_wa)) return;
      if (elPSemua.checked) terpilih.add(p.nomor_wa); else terpilih.delete(p.nomor_wa);
    });
    renderPeg();
  });

  elPTambah.addEventListener('click', function () {
    var peserta = hasilPeg.filter(function (p) { return terpilih.has(p.nomor_wa); })
      .map(function (p) { return { nomor_wa: p.nomor_wa, nama: p.nama }; });
    if (!peserta.length) return;

    elPTambah.disabled = true;
    api(url(K.urlTambah, kini), 'POST', { manual: false, peserta: peserta })
      .then(function (j) { dlgPeg.close(); notif(j.pesan); return muatSemua(); })
      .catch(function (er) { notif(er.message, true); elPTambah.disabled = false; });
  });

  /* ---------- Awal ---------- */
  try {
    var pesan = sessionStorage.getItem('kelToast');
    if (pesan) { sessionStorage.removeItem('kelToast'); notif(pesan); }
  } catch (e) { /* abaikan */ }

  if (K.pilih) elFKel.value = String(K.pilih);
  terapkanFilter();
  tampilHal(hal);
  muatSemua().catch(function (e) { notif(e.message, true); });
})();