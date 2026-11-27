/* Halaman Buat Rapat — SIUPIN
   Simpan sebagai: public/js/buat-rapat.js (ganti seluruh isi file lama)

   Perubahan:
   - Tab "Kelompok Sementara": kelompok hanya ada di halaman ini (memori browser),
     TIDAK dikirim ke server dan TIDAK disimpan ke database.
   - Anggota kelompok dipilih lewat pencarian pegawai atau input peserta dari luar.
   - Pencarian: respons usang diabaikan, ada status "Mencari…", Enter memilih hasil teratas.
   - Tampilan lebih bersih: tidak ada teks "Belum ada peserta dipilih." dan
     "Belum ada kelompok sementara." (wadah dibiarkan kosong sampai ada isinya).
*/
(function () {
  'use strict';

  var K = window.SIUPIN || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  var peserta = new Map();      // nomor_wa -> { nomor_wa, nama, luar, tags:Set }
  var kataTerpilih = '';        // kata kunci pencarian di daftar peserta terpilih

  // ----- Kelompok sementara (hanya di memori halaman) -----
  var grup = [];                // [{ id, nama, anggota:[{nomor_wa, nama, luar}] }]
  var draft = new Map();        // anggota kelompok yang sedang disusun: kunci -> { nomor_wa, nama, luar }
  var grupSeq = 0;

  var tersimpan = K.tersimpan || [];   // kelompok tersimpan (hanya untuk dipilih, tidak diubah dari sini)

  var elForm = $('#formRapat'), elHidden = $('#pesertaJson');
  var elJudul = $('#judulPeserta'), elChip = $('#chipPeserta');
  var elCari = $('#cariPegawai'), elHasil = $('#hasilCari');
  var elIsiKel = $('#isiKelompok');
  var elTempat = $('#id_tempat'), elTanggal = $('#tanggal'), elWaktu = $('#waktu'), elSelesai = $('#waktu_selesai'), elBadge = $('#badgeTempat');
  var elToast = $('#toast');
  var elLuarNama = $('#luarNama'), elLuarWa = $('#luarWa'), btnLuar = $('#btnTambahLuar');
  var elCariTerpilih = $('#cariTerpilih'), elKotakCariTerpilih = $('#kotakCariTerpilih');
  var elInfoCari = $('#infoCariTerpilih'), elHapusSemua = $('#btnHapusSemua');

  // elemen tab kelompok sementara
  var elNamaKel = $('#namaKelompok');
  var elCariAnggota = $('#cariAnggota'), elHasilAnggota = $('#hasilAnggota');
  var elGrupLuarNama = $('#grupLuarNama'), elGrupLuarWa = $('#grupLuarWa'), btnGrupLuar = $('#btnGrupLuar');
  var elChipDraft = $('#chipDraft'), elJumlahDraft = $('#jumlahDraft');
  var btnBuatGrup = $('#btnBuatGrup'), btnKosongDraft = $('#btnKosongDraft');
  var elDaftarGrup = $('#daftarGrup');

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
    return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('Gagal memuat data.');
      return r.json();
    });
  }
  function kirim(url, metode, body) {
    return fetch(url, {
      method: metode,
      credentials: 'same-origin',
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

  /* ---------- Nomor WA: samakan format (0812.. / +62812.. / 812.. -> 62812..) ---------- */
  function normNomor(n) {
    var d = String(n || '').replace(/\D+/g, '');
    if (!d) return null;
    if (d.indexOf('0') === 0) d = '62' + d.slice(1);
    else if (d.indexOf('8') === 0) d = '62' + d;
    return (d.indexOf('62') === 0 && d.length >= 10 && d.length <= 15) ? d : null;
  }
  function kunciNomor(n) { return normNomor(n) || String(n || '').trim(); }

  /** Cari peserta yang nomornya sama (walau format penulisannya beda) */
  function cariSama(nomor) {
    var langsung = peserta.get(nomor);
    if (langsung) return langsung;
    var n = normNomor(nomor);
    if (!n) return null;
    var hasil = null;
    peserta.forEach(function (e) { if (!hasil && normNomor(e.nomor_wa) === n) hasil = e; });
    return hasil;
  }

  /* ---------- Peserta terpilih ---------- */
  function tambah(daftar, tag) {
    daftar.forEach(function (p) {
      var e = cariSama(p.nomor_wa);
      if (!e) { e = { nomor_wa: p.nomor_wa, nama: p.nama, luar: false, tags: new Set() }; peserta.set(p.nomor_wa, e); }
      if (p.luar) e.luar = true;
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

  /** Cocokkan peserta dengan kata kunci (nama atau nomor WA) */
  function cocokCari(e, q, qd) {
    if (String(e.nama).toLowerCase().indexOf(q) >= 0) return true;
    if (qd.length >= 3) {
      var no = String(e.nomor_wa).replace(/\D+/g, '');
      if (no.indexOf(qd) >= 0) return true;
      if (qd.charAt(0) === '0' && no.indexOf('62' + qd.slice(1)) >= 0) return true;
    }
    return false;
  }

  function namaGrup(id) {
    for (var i = 0; i < grup.length; i++) if (grup[i].id === id) return grup[i].nama;
    return '';
  }

  /** Samakan kelompok sementara dengan peserta: anggota yang sudah dihapus dari peserta ikut hilang */
  function sinkronGrup() {
    grup.forEach(function (g) {
      g.anggota = g.anggota.filter(function (a) {
        var e = cariSama(a.nomor_wa);
        return e && e.tags.has('grup:' + g.id);
      });
    });
    grup = grup.filter(function (g) { return g.anggota.length > 0; });
  }

  function render() {
    var daftar = Array.from(peserta.values());
    var total = daftar.length;
    elJudul.textContent = 'Peserta Terpilih (' + total + ' orang)';
    elChip.textContent = '';

    if (elHapusSemua) elHapusSemua.hidden = !total;
    if (elKotakCariTerpilih) elKotakCariTerpilih.hidden = !total;
    if (!total && kataTerpilih) {
      kataTerpilih = '';
      if (elCariTerpilih) elCariTerpilih.value = '';
    }

    var q = kataTerpilih.toLowerCase();
    var qd = q.replace(/\D+/g, '');
    var tampil = q ? daftar.filter(function (e) { return cocokCari(e, q, qd); }) : daftar;

    if (elInfoCari) elInfoCari.textContent = q ? (tampil.length + ' dari ' + total + ' peserta') : '';

    // Saat belum ada peserta, wadah dibiarkan kosong (disembunyikan lewat CSS :empty).
    if (total && !tampil.length) {
      elChip.appendChild(buat('span', 'kosong-kecil', '"' + kataTerpilih + '" belum ada di daftar peserta.'));
    }

    tampil.forEach(function (e) {
      var s = buat('span', 'chip-orang');
      s.appendChild(ikon('user'));
      s.appendChild(document.createTextNode(e.nama + ' '));
      if (e.luar) s.appendChild(buat('em', 'tag-luar', 'Luar'));
      var namaG = [];
      e.tags.forEach(function (t) {
        if (t.indexOf('grup:') === 0) { var n = namaGrup(t.slice(5)); if (n) namaG.push(n); }
      });
      if (namaG.length) {
        var tg = buat('em', 'tag-grup', namaG[0] + (namaG.length > 1 ? ' +' + (namaG.length - 1) : ''));
        tg.title = 'Kelompok sementara: ' + namaG.join(', ');
        s.appendChild(tg);
      }
      var x = buat('button', 'x');
      x.appendChild(ikon('x'));
      x.type = 'button';
      x.setAttribute('aria-label', 'Hapus ' + e.nama);
      x.addEventListener('click', function () {
        peserta.delete(e.nomor_wa);
        render();
        segarkanSemuaHasil();
      });
      s.appendChild(x);
      elChip.appendChild(s);
    });

    // yang dikirim ke server tetap SELURUH peserta, bukan hanya hasil pencarian
    elHidden.value = JSON.stringify(daftar.map(function (e) { return { nomor_wa: e.nomor_wa, nama: e.nama }; }));

    sinkronGrup();
    renderGrup();
  }

  /* ---------- Cari & hapus semua di Peserta Terpilih ---------- */
  if (elCariTerpilih) {
    elCariTerpilih.addEventListener('input', function () {
      kataTerpilih = elCariTerpilih.value.trim();
      render();
    });
    elCariTerpilih.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
      if (e.key === 'Escape') { elCariTerpilih.value = ''; kataTerpilih = ''; render(); }
    });
  }

  if (elHapusSemua) {
    elHapusSemua.addEventListener('click', function () {
      if (!peserta.size) return;
      if (!window.confirm('Hapus semua ' + peserta.size + ' peserta dari rapat ini?')) return;
      peserta.clear();
      grup = [];
      $$('.acc-cek, .acc-semua').forEach(function (c) { c.checked = false; });
      kataTerpilih = '';
      if (elCariTerpilih) elCariTerpilih.value = '';
      render();
      segarkanSemuaHasil();
      notif('Semua peserta dihapus dari rapat.');
    });
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

  /* ---------- Pencarian pegawai (dipakai di dua tempat) ---------- */
  function pasangPencarian(input, box, opsi) {
    var timer, seq = 0, last = [];
    var wadah = input.closest('.kotak-cari');

    function tutup() { box.hidden = true; }

    function gambar(data) {
      last = data;
      box.textContent = '';
      box.hidden = false;
      if (!data.length) {
        box.appendChild(buat('div', 'kosong-kecil', 'Pegawai tidak ditemukan (atau belum punya nomor WA).'));
        return;
      }
      data.forEach(function (p) {
        var sudah = opsi.sudah(p);
        var b = buat('button', 'hasil-item' + (sudah ? ' sudah' : ''));
        b.type = 'button';
        b.appendChild(buat('b', null, p.nama));
        b.appendChild(buat('small', null, [p.unit, p.jabatan].filter(Boolean).join(' · ')));
        if (sudah) b.appendChild(buat('em', null, '✔'));
        b.addEventListener('click', function () {
          if (sudah) { notif(p.nama + ' sudah ada.', true); return; }
          opsi.pilih(p);
          gambar(last);
        });
        box.appendChild(b);
      });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { seq++; last = []; tutup(); return; }
      var mine = ++seq;
      timer = setTimeout(function () {
        box.textContent = '';
        box.hidden = false;
        box.appendChild(buat('div', 'kosong-kecil', 'Mencari…'));
        ambil(K.urlPegawai + '?q=' + encodeURIComponent(q)).then(function (data) {
          if (mine === seq) gambar(data);          // abaikan respons usang
        }).catch(function () {
          if (mine !== seq) return;
          box.textContent = '';
          box.appendChild(buat('div', 'kosong-kecil', 'Gagal memuat hasil. Coba lagi.'));
        });
      }, 250);
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();                        // jangan men-submit form rapat
        if (box.hidden) return;
        var p = last.filter(function (x) { return !opsi.sudah(x); })[0];
        if (p) { opsi.pilih(p); gambar(last); }
      } else if (e.key === 'Escape') {
        tutup();
      }
    });

    input.addEventListener('focus', function () {
      if (last.length && input.value.trim().length >= 2) gambar(last);
    });

    document.addEventListener('click', function (e) {
      if (wadah && !wadah.contains(e.target)) tutup();
    });

    return {
      segarkan: function () { if (!box.hidden && last.length) gambar(last); },
      reset: function () { seq++; last = []; input.value = ''; tutup(); }
    };
  }

  var pencPeserta = pasangPencarian(elCari, elHasil, {
    sudah: function (p) { return !!cariSama(p.nomor_wa); },
    pilih: function (p) { tambah([p], 'manual'); render(); }
  });

  var pencAnggota = pasangPencarian(elCariAnggota, elHasilAnggota, {
    sudah: function (p) { return draft.has(kunciNomor(p.nomor_wa)); },
    pilih: function (p) {
      draft.set(kunciNomor(p.nomor_wa), { nomor_wa: p.nomor_wa, nama: p.nama, luar: false });
      renderDraft();
    }
  });

  function segarkanSemuaHasil() { pencPeserta.segarkan(); pencAnggota.segarkan(); }

  /* ---------- Peserta dari luar (sementara, tidak masuk data pegawai) ---------- */
  function tambahLuar() {
    var nama = elLuarNama.value.trim();
    if (!nama) { notif('Nama peserta dari luar wajib diisi.', true); elLuarNama.focus(); return; }

    var wa = normNomor(elLuarWa.value);
    if (!wa) {
      notif('Nomor WA tidak valid. Contoh: 0812xxxxxxxx atau 62812xxxxxxxx.', true);
      elLuarWa.focus();
      return;
    }

    var sama = cariSama(wa);
    if (sama) { notif('Nomor itu sudah ada di daftar peserta (' + sama.nama + ').', true); elLuarWa.focus(); return; }

    tambah([{ nomor_wa: wa, nama: nama, luar: true }], 'luar');
    render();
    elLuarNama.value = '';
    elLuarWa.value = '';
    elLuarNama.focus();
    notif(nama + ' ditambahkan sebagai peserta dari luar.');
  }

  btnLuar.addEventListener('click', tambahLuar);
  [elLuarNama, elLuarWa].forEach(function (el) {
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); tambahLuar(); }
    });
  });

  /* ---------- Akordeon: unit / jabatan / kelompok tersimpan ---------- */
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
    if (!elIsiKel) return;
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

  /* =====================================================================
     KELOMPOK SEMENTARA — hanya di memori halaman, tidak pernah dikirim ke server
     ===================================================================== */

  /** Anggota kelompok yang sedang disusun */
  function renderDraft() {
    elChipDraft.textContent = '';
    elJumlahDraft.textContent = draft.size + ' orang';

    // Saat kosong, wadah dibiarkan kosong (gaya garis putus-putus lewat CSS :empty).
    if (!draft.size) return;

    draft.forEach(function (a, kunci) {
      var s = buat('span', 'chip-orang');
      s.appendChild(ikon('user'));
      s.appendChild(document.createTextNode(a.nama + ' '));
      if (a.luar) s.appendChild(buat('em', 'tag-luar', 'Luar'));
      var x = buat('button', 'x');
      x.type = 'button';
      x.appendChild(ikon('x'));
      x.setAttribute('aria-label', 'Keluarkan ' + a.nama);
      x.addEventListener('click', function () {
        draft.delete(kunci);
        renderDraft();
        pencAnggota.segarkan();
      });
      s.appendChild(x);
      elChipDraft.appendChild(s);
    });
  }

  function tambahLuarGrup() {
    var nama = elGrupLuarNama.value.trim();
    if (!nama) { notif('Nama anggota wajib diisi.', true); elGrupLuarNama.focus(); return; }
    var wa = normNomor(elGrupLuarWa.value);
    if (!wa) { notif('Nomor WA tidak valid. Contoh: 0812xxxxxxxx.', true); elGrupLuarWa.focus(); return; }
    if (draft.has(wa)) { notif('Nomor itu sudah ada di anggota kelompok (' + draft.get(wa).nama + ').', true); return; }

    draft.set(wa, { nomor_wa: wa, nama: nama, luar: true });
    renderDraft();
    elGrupLuarNama.value = '';
    elGrupLuarWa.value = '';
    elGrupLuarNama.focus();
  }
  btnGrupLuar.addEventListener('click', tambahLuarGrup);
  [elGrupLuarNama, elGrupLuarWa].forEach(function (el) {
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); tambahLuarGrup(); }
    });
  });

  btnKosongDraft.addEventListener('click', function () {
    if (!draft.size) return;
    draft.clear();
    renderDraft();
    pencAnggota.segarkan();
  });

  /** Jadikan anggota yang sedang disusun sebagai kelompok sementara + masukkan ke Peserta Terpilih */
  btnBuatGrup.addEventListener('click', function () {
    var nama = elNamaKel.value.trim();
    if (!nama) { notif('Nama kelompok wajib diisi.', true); elNamaKel.focus(); return; }
    if (!draft.size) { notif('Tambahkan minimal satu anggota kelompok.', true); elCariAnggota.focus(); return; }
    var kembar = grup.some(function (g) { return g.nama.toLowerCase() === nama.toLowerCase(); });
    if (kembar) { notif('Nama kelompok "' + nama + '" sudah ada di rapat ini.', true); elNamaKel.focus(); return; }

    var g = { id: 'g' + (++grupSeq), nama: nama, anggota: Array.from(draft.values()) };
    grup.push(g);
    tambah(g.anggota, 'grup:' + g.id);

    var n = g.anggota.length;
    draft.clear();
    elNamaKel.value = '';
    pencAnggota.reset();
    renderDraft();
    render();
    pencPeserta.segarkan();
    notif('Kelompok "' + nama + '" (' + n + ' orang) dibuat dan dimasukkan ke peserta rapat.');
  });

  elNamaKel.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); btnBuatGrup.click(); }
  });

  function hapusGrup(g) {
    lepas('grup:' + g.id);
    grup = grup.filter(function (x) { return x.id !== g.id; });
    render();
    segarkanSemuaHasil();
    notif('Kelompok "' + g.nama + '" dihapus.');
  }

  function keluarkanDariGrup(g, a) {
    var e = cariSama(a.nomor_wa);
    if (e) {
      e.tags.delete('grup:' + g.id);
      if (!e.tags.size) peserta.delete(e.nomor_wa);   // hanya dihapus bila tidak punya sumber lain
    }
    render();
    segarkanSemuaHasil();
  }

  /** Daftar kelompok sementara di rapat ini (wadahnya disembunyikan di Blade; tetap dirender agar kode lain aman) */
  function renderGrup() {
    if (!elDaftarGrup) return;
    elDaftarGrup.textContent = '';
    if (!grup.length) return;   // tidak ada teks placeholder

    grup.forEach(function (g) {
      var kartu = buat('div', 'grup-kartu');

      var kepala = buat('div', 'grup-kepala');
      var judul = buat('span');
      judul.appendChild(buat('b', null, g.nama + ' '));
      judul.appendChild(buat('small', null, '(' + g.anggota.length + ' orang)'));
      var hapus = buat('button', 'btn-hapus-semua');
      hapus.type = 'button';
      hapus.appendChild(ikon('x'));
      hapus.appendChild(document.createTextNode(' Hapus Kelompok'));
      hapus.addEventListener('click', function () { hapusGrup(g); });
      kepala.appendChild(judul);
      kepala.appendChild(hapus);
      kartu.appendChild(kepala);

      var isi = buat('div', 'grup-isi');
      g.anggota.forEach(function (a) {
        var s = buat('span', 'chip-orang');
        s.appendChild(ikon('user'));
        s.appendChild(document.createTextNode(a.nama + ' '));
        if (a.luar) s.appendChild(buat('em', 'tag-luar', 'Luar'));
        var x = buat('button', 'x');
        x.type = 'button';
        x.appendChild(ikon('x'));
        x.setAttribute('aria-label', 'Keluarkan ' + a.nama + ' dari kelompok');
        x.addEventListener('click', function () { keluarkanDariGrup(g, a); });
        s.appendChild(x);
        isi.appendChild(s);
      });
      kartu.appendChild(isi);
      elDaftarGrup.appendChild(kartu);
    });
  }

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
    if (!elTempat.value || elTempat.value === 'luar' || !elTanggal.value || !elWaktu.value) return;
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

  /* ---------- Tampilan tanggal & waktu ---------- */
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
    var terakhirPilih = 0;

    el.addEventListener('input', segarkanTampilan);
    el.addEventListener('change', function () {
      terakhirPilih = Date.now();
      segarkanTampilan();
    });

    if (el.tagName === 'SELECT') return;

    el.addEventListener('click', function () {
      if (Date.now() - terakhirPilih < 400) return;
      try { el.showPicker(); } catch (e) { /* abaikan */ }
    });
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
      return;
    }
    // anggota kelompok yang disusun tapi belum dibuat tidak ikut terkirim
    if (draft.size && !window.confirm(draft.size + ' anggota kelompok belum dibuat dan tidak akan ikut dikirim. Lanjutkan?')) {
      e.preventDefault();
    }
  });
  elForm.addEventListener('reset', function () {
    setTimeout(function () {
      peserta.clear();
      grup = [];
      draft.clear();
      $$('.acc-cek, .acc-semua').forEach(function (c) { c.checked = false; });
      elNamaKel.value = '';
      elLuarNama.value = '';
      elLuarWa.value = '';
      elGrupLuarNama.value = '';
      elGrupLuarWa.value = '';
      pencPeserta.reset();
      pencAnggota.reset();
      kataTerpilih = '';
      if (elCariTerpilih) elCariTerpilih.value = '';
      renderDraft(); render(); aturMetode(); cekTempat(); segarkanTampilan();
      if (elNamaFile) elNamaFile.textContent = 'Pilih File';
    }, 0);
  });

  /* ---------- Awal ---------- */
  (K.pesertaLama || []).forEach(function (p) { tambah([{ nomor_wa: p.nomor_wa, nama: p.nama }], 'manual'); });
  renderTersimpan();
  renderDraft();
  render();
  aturMetode();
  cekTempat();
  segarkanTampilan();
  muatStatus();
  if (K.pesanSukses) notif(K.pesanSukses);
})(); 