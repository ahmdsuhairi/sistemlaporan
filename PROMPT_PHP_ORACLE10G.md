# PROMPT ADAPTASI: SISTEM DASHBOARD TUNGGAKAN & TERIMAAN IKUT JALAN (PHP + ORACLE 10g)

> Prompt ini telah diubah suai daripada spesifikasi PDF asal (HTML/CSS Vanilla + Google Sheets + Apps Script) kepada persekitaran **PHP + Oracle 10g** menggunakan sambungan TNS `ORCLVM55` dan skrip SQL `DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql`.

---

## 1. Peranan & Teknologi
Sebagai **Pembangun Sistem Web PHP + HTML + CSS + JavaScript Vanilla** yang berpengalaman 15 tahun dalam pembangunan sistem kewangan/hasil Pihak Berkuasa Tempatan (PBT) serta integrasi pangkalan data **Oracle 10g**, bina sebuah aplikasi web **Sistem Dashboard Tunggakan & Terimaan Ikut Jalan** yang kemas, moden, responsif (PWA-ready) dan profesional seperti reka bentuk dashboard rujukan.

- **Backend**: PHP 8.x (sokongan dwi-pemacu `OCI8` dan `PDO_OCI` yang serasi dengan Oracle Database 10g tanpa rangka kerja berat)
- **Frontend**: HTML5 + CSS3 Vanilla + JavaScript Vanilla (termasuk carta SVG/Canvas asli tanpa kebergantungan CDN luar supaya boleh beroperasi dalam rangkaian intranet)
- **Pangkalan Data**: Oracle Database 10g (`ORCLVM55`)

---

## 2. Konfigurasi Sambungan Oracle 10g
Gunakan deskriptor sambungan TNS berikut (disimpan selamat dalam `config.local.php` atau pemboleh ubah persekitaran, bukan hardcoded kata laluan dalam kod frontend):

```text
ORCLVM55 =
  (DESCRIPTION =
    (ADDRESS_LIST =
      (ADDRESS = (PROTOCOL = TCP)(HOST = 172.16.33.55)(PORT = 1521))
    )
    (CONNECT_DATA =
      (SERVICE_NAME = orclvm53)
      (SERVER = DEDICATED)
    )
  )
```

---

## 3. Sumber Data & Skrip SQL Rujukan (Read-Only)
Data ditarik terus secara baca-sahaja (*read-only*) daripada Oracle 10g melibatkan jadual:
- `mtkn.hvnduk2 c` (Induk Akaun Pegangan)
- `mtkn.hlpyt1 d` (Transaksi Penyata / Lejar)
- `spbt.fjalan e` (Rujukan Kod & Nama Jalan)

### Kuiri Teras (`DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql`)
```sql
SELECT
  c.peg_akaun,
  c.peg_jlkod,
  e.jln_jnama,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'12010',d.pyt_amaun,0)) + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS bil_tunggakan,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'22010',d.pyt_amaun,0)) - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS terima_tunggakan,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'12010',d.pyt_amaun,0)) + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0)))
    - (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'22010',d.pyt_amaun,0)) - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS baki_tunggakan,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11010',d.pyt_amaun,0)) + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS bil_semasa,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'21010',d.pyt_amaun,0)) - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS terima_semasa,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11010',d.pyt_amaun,0)) + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0)))
    - (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'21010',d.pyt_amaun,0)) - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS baki_semasa
FROM mtkn.hvnduk2 c, mtkn.hlpyt1 d, spbt.fjalan e
WHERE c.jpk_itkod = '11010'
  AND c.peg_akaun = d.pyt_akaun
  AND c.peg_jlkod = e.jln_jlkod
  AND c.peg_statf != 'H'
  -- Tapisan dinamik berparameter:
  -- AND c.peg_jlkod = :jlkod
  -- AND UPPER(c.peg_akaun) LIKE UPPER(:akaun)
GROUP BY c.peg_akaun, c.peg_jlkod, e.jln_jnama
ORDER BY c.peg_akaun
```

> **Nota Keserasian Oracle 10g**: Semua penomboran halaman (*pagination*) dan kuiri Top-N wajib menggunakan `ROWNUM` (bukan `FETCH FIRST`), serta menggunakan pembolehubah terikat (*bind variables*) bagi mengelakkan *SQL Injection*.

---

## 4. Keperluan Modul & Antara Muka (Mengikut Struktur PDF)

1. **Susun Atur, Tema & PWA**
   - Sesuai untuk paparan **desktop dan mobile (PWA)** secara fleksibel (sertakan `manifest.json` & *service worker*).
   - **Sidebar secara lalai (default) adalah minimize**, serta mempunyai butang fungsi **minimize dan maximize** (dan *drawer* untuk skrin telefon).
   - Sokongan **Light Theme dan Dark Theme** dengan nisbah kontras teks dan latar belakang yang tinggi, kemas dan profesional.
   - Paparan **semua Jalan serentak secara lalai**, dengan penapis dropdown dinamik **Jalan (`PEG_JLKOD` / `JLN_JNAMA`)** dan carian **No. Akaun (`PEG_AKAUN`)**.

2. **Tab 1: Dashboard Utama**
   - Kad KPI ringkasan keseluruhan (Jumlah Akaun, Bil Tunggakan, Terima Tunggakan, Baki Tunggakan, Bil Semasa, Terima Semasa, Baki Semasa, Kadar Kutipan %).
   - Filter carian pantas mengikut **Jalan (`PEG_JLKOD`)** dan **No. Akaun (`PEG_AKAUN`)** — apabila satu No. Akaun dipilih/dicari, paparkan kad profil akaun tersebut bersama pecahan penuh Tunggakan & Semasa.
   - **2 Kad Carta Prestasi** bersebelahan menyerupai contoh gambar dalam PDF:
     - Kad 1: **PRESTASI TUNGGAKAN** (ringkasan Bil, Terimaan, Baki + % Kutipan & graf garis dengan label nilai serta lorekan gradien)
     - Kad 2: **PRESTASI SEMASA** (ringkasan Bil, Terimaan, Baki + % Kutipan & graf garis dengan label nilai serta lorekan gradien)

3. **Tab 2: Analisis Jalan (adaptasi daripada Analisis Subjek)**
   - Bar penapis: Pilih **Jalan** (Semua / Jalan tertentu), **Status Akaun** (Semua / Tertunggak / Selesai / Lebih Bayar), dan carian No. Akaun.
   - **4 Kad KPI Beraksen**: Jumlah Akaun, Purata Kadar Kutipan (%), Akaun Selesai / Bersih, dan Akaun Tertunggak.
   - **2 Carta Analisis**:
     - Carta Bar berwarna: Taburan julat baki / perbandingan komponen Bil, Terimaan & Baki.
     - Carta Donat (Ring): Nisbah Akaun Selesai vs Tertunggak dengan jumlah keseluruhan di tengah donat.
   - **Senarai 5 Akaun Tunggakan Tertinggi** (serta 5 Akaun Kutipan Terbaik) mengikut tapisan Jalan yang dipilih.

4. **Tab 3: Senarai Akaun (adaptasi daripada Senarai Pelajar)**
   - Jadual penuh berhalaman (*paginated*) memaparkan kolum tepat seperti output SQL:
     `Row #`, `PEG_AKAUN`, `PEG_JLKOD`, `JLN_JNAMA`, `BIL_TUNGGAKAN`, `TERIMA_TUNGGAKAN`, `BAKI_TUNGGAKAN`, `BIL_SEMASA`, `TERIMA_SEMASA`, `BAKI_SEMASA`, serta status baki.
   - Sokongan susunan kolum (*sorting*), penapis Jalan, carian No. Akaun, penapis Status, **Eksport CSV (UTF-8 untuk Excel)** dan **Cetak Laporan**.

5. **Tab 4: Pentadbiran & Diagnostik Oracle (Read-Only)**
   - Memaparkan status sambungan ke pelayan `ORCLVM55` (`172.16.33.55:1521/orclvm53`), pemacu PHP yang aktif (`OCI8` / `PDO_OCI`), dan masa tindak balas kuiri.
   - Butang **Uji Sambungan Oracle**, paparan rujukan skrip SQL yang digunakan, serta mod pratonton **Data Contoh (Demo Mode)** yang dilabel dengan jelas sekiranya sambungan ke pelayan intranet belum diaktifkan.

6. **Tab 5: Tetapan (Settings)**
   - Bahagian pengesahan & tetapan **ID dan Password (Mock Login)** dalam sesi.
   - Pilihan lalai tema (Light/Dark), keadaan sidebar, format paparan nombor (RM), dan pilihan mod sumber data (Oracle 10g Langsung / Mod Demo).

---

## 5. Seni Bina Modular Gaya Portal ePBT (Pelancar Laporan SQL)
- Sediakan **Laman Utama Modul (Portal Modul)** berpandukan gaya kad modul ePBT (kad berwarna dengan ikon bulat bertindih di bahagian atas: *ID Pelanggan*, *Sistem Aduan*, *Sub-Sistem Hasil*, *Sub-Sistem Perakaunan*, *Menu Penyelenggaraan Sistem*, *Sistem GST/Cukai*, *EIS*, *Audit Log*, *Penyelenggaraan Kata Laluan*, *Integrasi Sistem*, *Penerimaan Bayaran Agensi*, dan *ePBT Report*).
- Bina daftar modul dalam `modules/registry.php` dan enjin pelancar laporan automatik dalam `src/ReportEngine.php` yang mengimbas fail definisi laporan di `modules/reports/*.php` supaya laporan Oracle 10g baharu boleh ditambah secara *plug-and-play* lengkap dengan penapis, *pagination* `ROWNUM`, dan Eksport CSV.


