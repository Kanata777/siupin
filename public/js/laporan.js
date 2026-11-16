/* Halaman Laporan & Rekap — SIUPIN */
(function () {
  'use strict';

  var K = window.LAPORAN || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  var form = $('#formLaporan');

  function buat(tag, kelas, teks) {
    var e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (teks != null) e.textContent = teks;
    return e;
  }
  function angka(n) { return Number(n).toLocaleString('id-ID'); }

  /* ---------- Filter ---------- */
  $$('select', form).forEach(function (s) { s.addEventListener('change', function () { form.submit(); }); });

  // Enter di dalam formulir tidak boleh memicu unduhan
  form.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

  var elDari = $('#fDari'), elSampai = $('#fSampai'), elTeks = $('#teksRentang');
  function fmt(v) {
    return new Date(v + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  function segarkanRentang() {
    var d = elDari.value, s = elSampai.value, t = '';
    if (d && s) t = fmt(d) + ' – ' + fmt(s);
    else if (d) t = 'Mulai ' + fmt(d);
    else if (s) t = 'Sampai ' + fmt(s);
    elTeks.textContent = t || 'Semua tanggal';
    elTeks.classList.toggle('kosong', !t);
  }
  [elDari, elSampai].forEach(function (el) {
    el.addEventListener('click', function () { try { el.showPicker(); } catch (e) { /* abaikan */ } });
    el.addEventListener('change', function () {
      if (elDari.value && elSampai.value && elDari.value > elSampai.value) {
        if (el === elDari) elSampai.value = elDari.value; else elDari.value = elSampai.value;
      }
      segarkanRentang();
      form.submit();
    });
  });
  segarkanRentang();

  /* ---------- Pilihan laporan ---------- */
  var ceks = $$('.cek-jenis'), semua = $('#cekSemua'), btn = $('#btnUnduh'),
      ringkas = $('#uRingkas'), btnTeks = $('#btnTeks');

  var NAMA_TOMBOL = { xlsx: 'Unduh Excel (.xlsx)', csv: 'Unduh CSV', pdf: 'Buka Tampilan Cetak / PDF' };

  function formatDipilih() {
    var r = $('input[name="format"]:checked');
    return r ? r.value : 'xlsx';
  }

  function segarkanPilihan() {
    var n = 0, baris = 0;
    ceks.forEach(function (c) {
      if (c.checked) { n++; baris += parseInt(c.closest('.lap-item').dataset.total, 10) || 0; }
    });
    semua.checked = n > 0 && n === ceks.length;
    semua.indeterminate = n > 0 && n < ceks.length;
    btn.disabled = n === 0;
    ringkas.textContent = n ? n + ' laporan dipilih · ' + angka(baris) + ' baris' : 'Belum ada laporan dipilih';
    btnTeks.textContent = n ? NAMA_TOMBOL[formatDipilih()] + (n > 1 ? ' (' + n + ' laporan)' : '') : 'Unduh Laporan Terpilih';
  }

  ceks.forEach(function (c) { c.addEventListener('change', segarkanPilihan); });
  semua.addEventListener('change', function () {
    ceks.forEach(function (c) { c.checked = semua.checked; });
    segarkanPilihan();
  });
  $$('input[name="format"]').forEach(function (r) { r.addEventListener('change', segarkanPilihan); });

  // tujuan unduhan bergantung pada format: Excel/CSV diunduh, PDF dibuka di tab baru untuk dicetak
  btn.addEventListener('click', function () {
    if (formatDipilih() === 'pdf') {
      btn.formAction = K.urlCetak;
      btn.formTarget = '_blank';
    } else {
      btn.formAction = K.urlUnduh;
      btn.formTarget = '_self';
    }
  });

  /* ---------- Pratinjau ---------- */
  var elTabel = $('#pTabel'), elJudul = $('#pJudul'), elInfo = $('#pInfo'), inLihat = $('#inLihat');

  function pesanKosong(teks) {
    elTabel.textContent = '';
    var k = buat('div', 'kosong-lap');
    k.appendChild(buat('span', null, teks));
    elTabel.appendChild(k);
  }

  function tampilkan(j) {
    elJudul.textContent = 'Pratinjau: ' + j.judul;
    elInfo.textContent = j.total
      ? 'Menampilkan ' + angka(j.baris.length) + ' dari ' + angka(j.total) + ' baris' + (j.total > j.baris.length ? ' (unduh untuk data lengkap)' : '')
      : '';

    if (!j.baris.length) { pesanKosong('Tidak ada data pada filter ini.'); return; }

    var tabel = buat('table');
    var kepala = buat('tr');
    j.kolom.forEach(function (k) { kepala.appendChild(buat('th', null, k)); });
    tabel.appendChild(buat('thead')).appendChild(kepala);

    var badan = buat('tbody');
    j.baris.forEach(function (b, i) {
      var tr = buat('tr');
      tr.appendChild(buat('td', null, String(i + 1)));
      b.forEach(function (sel) {
        var td = buat('td', typeof sel === 'number' ? 'angka' : null, sel == null ? '' : String(sel));
        td.title = sel == null ? '' : String(sel);
        tr.appendChild(td);
      });
      badan.appendChild(tr);
    });
    tabel.appendChild(badan);

    elTabel.textContent = '';
    elTabel.appendChild(tabel);
  }

  function lihat(jenis) {
    inLihat.value = jenis;
    $$('.lap-item').forEach(function (it) { it.classList.toggle('aktif', it.dataset.jenis === jenis); });
    pesanKosong('Memuat pratinjau…');

    var data = new FormData(form);
    var p = new URLSearchParams();
    ['dari', 'sampai', 'unit', 'tempat', 'status'].forEach(function (k) { p.set(k, data.get(k) || ''); });
    p.set('jenis', jenis);

    fetch(K.urlPratinjau + '?' + p.toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('Pratinjau tidak dapat dimuat.'); return r.json(); })
      .then(tampilkan)
      .catch(function (e) { elJudul.textContent = 'Pratinjau Laporan'; elInfo.textContent = ''; pesanKosong(e.message); });
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-lihat]');
    if (b) { e.stopPropagation(); lihat(b.dataset.lihat); return; }
    var it = e.target.closest('.lap-item');
    if (it && !e.target.closest('input')) lihat(it.dataset.jenis);
  });

  /* ---------- Awal ---------- */
  segarkanPilihan();
  if (K.lihat) lihat(K.lihat);
})();