# อิ่มดี · QR Self-Ordering (SQL Server POS Integration)

ระบบเว็บแอปพลิเคชันสำหรับสั่งอาหารผ่าน QR Code พัฒนาด้วย PHP 8.5, HTML5, Tailwind CSS, Alpine.js และ SweetAlert2 เชื่อมต่อโดยตรงกับฐานข้อมูล Microsoft SQL Server ของระบบ POS ร้านอาหาร

## เปิดใช้งาน

ใน PowerShell ที่โฟลเดอร์โปรเจกต์:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8080 -t public config/router.php
```

- **สแกน QR Code ตามเลขที่บิล (`Fn_Doc_No`)**:
  - บิลเปิดอยู่ (`Doc_Sts = 0` พร้อมสั่งอาหาร): `http://127.0.0.1:8080/?Fn_Doc_No=68005642`
  - บิลออกใบเสร็จแล้ว (`Doc_Sts = 1` ปิดบิลแล้ว): `http://127.0.0.1:8080/?Fn_Doc_No=68005643`
  - บิลยกเลิก (`Doc_Sts = 3` ยกเลิกบิล): `http://127.0.0.1:8080/?Fn_Doc_No=68005641`
- **เข้าใช้งานตามหมายเลขโต๊ะ (`table`)**:
  - โต๊ะ 2: `http://127.0.0.1:8080/?table=2`
  - โต๊ะ 12: `http://127.0.0.1:8080/?table=12`

## คุณสมบัติและการเชื่อมต่อฐานข้อมูล SQL Server

1. **ตรวจสอบสถานะบิลเมื่อสแกน QR Code (`Fnt_Header`)**:
   - ค้นหาบิลตาม SQL:
     `SELECT * FROM Fnt_Header b WHERE b.Fn_Doc_No = '$Fn_Doc_No' AND b.Doc_Sts = 0 AND b.Branch_ID = '001'`
   - **`Doc_Sts = 0` (เปิดบิล)**: อนุญาตให้สั่งอาหารได้ตามปกติ
   - **`Doc_Sts = 1` (ออกใบเสร็จแล้ว)**: บิลปิดแล้ว แจ้งเตือนและล็อกไม่ให้สั่งอาหารเพิ่ม แต่สามารถเปิดดูประวัติรายการอาหารที่สั่งในบิลได้
   - **`Doc_Sts = 3` (ยกเลิก)**: บิลถูกยกเลิก แจ้งเตือนให้ติดต่อพนักงาน
2. **เชื่อมต่อกับฐานข้อมูล SQL Server จริง (`192.168.2.4` / `DBFood`)**:
   - ใช้ไดรเวอร์ `ODBC Driver 18 for SQL Server` ร่วมกับโมดูล PHP `pdo_odbc`
   - รองรับตัวอักษรภาษาไทยสมบูรณ์แบบ (ถอดรหัส `varbinary` / `UTF-16LE` และแปลงเป็น Hex Literal ป้องกันปัญหาตัวอักษรเพี้ยนเป็น `?`)
3. **โหลดหมวดหมู่และรายการอาหารจริงจากตาราง `Trade` และ `Type`**:
   - ดึงหมวดหมู่จากตาราง `Type` (10 หมวดหมู่)
   - ดึงรายการอาหารทั้งหมด 53 รายการจากตาราง `Trade` (`Trade_ID`, `Trade_Name`, `Sale_Price`, `Unit_Name`, `Food_Type`, `Link_Part_image`)
   - เรียงลำดับตามรหัส `Trade_ID` และผูกหมวดหมู่ตามรหัสหมวดหมู่ 3 หลักแรกอัตโนมัติ (เช่น `001xxxx` อาหารจานหลัก, `008xxxx` กาแฟ)
   - แสดงหน่วยนับจริงตามตาราง (`Unit_Name`: จาน, ถ้วย, แก้ว) บนการ์ดเมนูและหน้ารายละเอียดอาหาร
4. **ส่งออเดอร์เข้า POS และตัดสต็อกสินค้าอัตโนมัติ**:
   - สร้างเลขที่บิลอัตโนมัติจาก `PBM_CTRL` (`PB_RUN_CD = 'FIN'`) หากยังไม่มีบิลเปิด
   - บันทึกรายการลง `Fnt_Detail` (`Print_STS = ''`, `Food_Que_STS = ''`, `Trade_Point`)
   - **ตัดสต็อกในตาราง `Trade` ทันที**:
     `UPDATE Trade SET Qty = Qty - '$Qty_Cut_Stock' WHERE (Trade_ID = '$Trade_ID') OR (Group_Pro = '$Group_Pro' AND Group_Pro <> '')`
   - **บันทึกลงตารางความเคลื่อนไหวสต็อก `Movement`**:
     ลงรายการ `Reference = 'ขาย-web'`, `Pay_Qty`, `Cost_Price`, `Balance_Qty`
   - **ตัดวัตถุดิบและส่วนประกอบอาหาร (`Product_Ingredient`)**:
     - ค้นหาวัตถุดิบของเมนูอาหารจาก `Product_Ingredient`
     - ตัดยอดวัตถุดิบใน `Trade`: `UPDATE Trade SET Qty = Qty - '$QtyRaw' WHERE Branch_Id = '001' AND Trade_ID = '$Trade_ID_Raw'`
     - บันทึกความเคลื่อนไหวลงตาราง `Movement`: `Reference = 'ตัดRAW-web'`, `Trade_id = '$Trade_ID_Raw'`, `Pay_Qty = '$QtyRaw'`, `Cost_Price`, `Sale_Price`, `Balance_Qty`
   - คำนวณยอดรวมและภาษีมูลค่าเพิ่ม 7% (VAT) ลง `Fnt_Header`
   - ทำงานภายใต้ Database Transaction เดียวกัน หากเกิดข้อผิดพลาดจะ Rollback ทั้งหมดทันที
5. **ดูประวัติการสั่งอาหารจริง**:
   - ดึงรายการที่สั่งจริงจาก `Fnt_Detail` ของบิลปัจจุบัน และจัดกลุ่มตามรอบการสั่ง
6. **สลับโหมดการทำงานได้ง่ายใน `.env`**:
   - `POS_MODE=live` : เชื่อมต่อระบบ POS SQL Server จริง
   - `POS_MODE=demo` : ทำงานแบบโหมดทดลองในเบราว์เซอร์

## โครงสร้างโปรเจกต์

```text
service-ordering/
├── .htaccess                 # กฎ Rewrite หลัก และป้องกันการเข้าถึงโฟลเดอร์ภายใน
├── web.config                # คอนฟิก IIS URL Rewrite
├── composer.json             # จัดการ dependencies ของ PHP
├── app/                      # Backend Logic ตามสถาปัตยกรรม MVC
│   ├── Controllers/          # ตัวควบคุมคำขอ (main, staff, auth, bill, menu, order ฯลฯ)
│   ├── Database/             # การเชื่อมต่อฐานข้อมูล SQL Server (db.php)
│   ├── Middlewares/          # ตัวกรองสิทธิ์ เช่น ตรวจสอบเซสชันพนักงาน (staff-access.php)
│   ├── Security/             # ความปลอดภัย เซสชัน และ rate limiter
│   ├── Services/             # Business Logic สำหรับบิล โต๊ะ เมนู ออเดอร์
│   └── Views/                # แม่แบบหน้าจอ HTML (app.php และหน้าพนักงานใน Staff/)
├── config/                   # ไฟล์ตั้งค่าระบบ ฐานข้อมูล และ development router
├── database/migrations/      # ไฟล์ migration สำหรับตรวจสอบและเตรียมฐานข้อมูล
├── docs/                     # เอกสารระบบ (README.md, design.md, Agent.md)
├── index.php                 # Front Controller หลักของระบบ
├── public/                   # ไฟล์สาธารณะ (assets และ PWA)
│   ├── manifest.php          # PWA Manifest
│   ├── sw.js                 # Service Worker สำหรับ PWA และโหมดออฟไลน์
│   ├── offline.html          # หน้าจอแสดงเมื่อไม่มีการเชื่อมต่อเครือข่าย
│   └── assets/               # Static assets (css, js, images, fonts)
├── routes/                   # ตารางกำหนดเส้นทาง URL
│   ├── web.php               # เส้นทางหน้าเว็บ (Customer, Staff)
│   └── api.php               # เส้นทาง API ทั้งหมด
├── storage/                  # ไฟล์อัปโหลด (รูปสินค้า), แคช และ rate limiter
└── tests/                    # ชุดทดสอบอัตโนมัติ (Pest / PHPUnit)
```

### รูปแบบเส้นทาง URL (Routing Structure)

- **ฝั่งลูกค้า (Customer)** — สแกน QR Code เข้าตรงตามเลขบิล ไม่ต้องล็อกอิน ระบบดึงโต๊ะให้อัตโนมัติ:
  - เมนูอาหาร: `http://localhost/service-ordering/{bill}#menu` (เช่น `.../68005654#menu`)
  - ตะกร้าสินค้า: `http://localhost/service-ordering/{bill}#cart`
  - ประวัติการสั่ง: `http://localhost/service-ordering/{bill}#history`
- **ฝั่งพนักงาน (Staff)** — มีระบบตรวจสอบสิทธิ์และเซสชัน (บังคับล็อกอิน):
  - หน้าหลักพนักงาน (Portal): `http://localhost/service-ordering/staff/`
  - หน้ารวมโต๊ะ (Table Selector): `http://localhost/service-ordering/staff/tables/`
  - สั่งอาหารตามโต๊ะ/บิล: `http://localhost/service-ordering/staff/tables/{bill}#menu`
  - จัดการเมนูและรูปอาหาร: `http://localhost/service-ordering/staff/menus/`

กำหนด Web Root ของ Apache หรือ IIS เป็นรากโปรเจ็กต์ และเปิด Apache mod_rewrite หรือ IIS URL Rewrite Module ตามลำดับ กฎใน .htaccess และ web.config ส่ง URL ของ assets และ PWA ไปยัง public/ และส่งเส้นทางหน้าเว็บและ API ไปยัง index.php โดยป้องกันการเข้าถึงไฟล์ภายในโดยตรง

ลิงก์บิลลูกค้ารองรับ `/{hash}` และ `/{เลขบิล}` ส่วนพนักงานใช้ `/staff/tables/{hash}` หรือ `/staff/tables/{เลขบิล}` โดยต้องเข้าสู่ระบบก่อน ระบบค้น hash จาก `Fnt_Header.Fn_Qrcode_Order` ภายในสาขาที่กำหนด และใช้ hash เป็น URL เริ่มต้นเมื่อเปิดหรือเลือกโต๊ะ หากบิลเดิมไม่มี hash จะใช้เลขบิล สูตร hash คำนวณจากเลขบิลและไม่ใช่ token ลับสำหรับยืนยันสิทธิ์

สำหรับ IIS ต้องติดตั้ง URL Rewrite Module เพื่อใช้ทุกเส้นทางที่ไม่ใช่ไฟล์ static ไฟล์ `web.config` ที่รากโปรเจ็กต์กำหนดกฎ rewrite ทั้งนี้ต้องตรวจ handler PHP, สิทธิ์ storage และทดสอบการ rewrite บนเซิร์ฟเวอร์จริง
ติดตั้งเป็น PWA

เว็บไซต์มี manifest, ไอคอนสำหรับ Android/iOS และ service worker สำหรับแสดงหน้าแจ้งเตือนเมื่อไม่มีเครือข่าย โดยไม่แคชข้อมูลบิล เซสชัน ตะกร้า หรือ API สั่งอาหาร

- ทดสอบบนเครื่องพัฒนาผ่าน `http://localhost:8080` หรือ `http://127.0.0.1:8080`
- สำหรับมือถือและแท็บเล็ตที่เข้า IIS ผ่านเครือข่าย ต้องใช้ HTTPS ที่อุปกรณ์เชื่อถือได้และตรงกับชื่อหรือ IP ที่เข้าถึง เช่น `https://ชื่อเซิร์ฟเวอร์:พอร์ต/` การใช้ `http://192.168.2.4:8086` จากอุปกรณ์อื่นไม่ใช่ secure context สำหรับ service worker
- ติดตั้งผ่านเมนูเบราว์เซอร์: บน iPhone/iPad ใช้ Safari → แชร์ → เพิ่มไปยังหน้าจอโฮม; บน Android/คอมใช้เมนูติดตั้งหรือเพิ่มไปยังหน้าจอโฮมตามเบราว์เซอร์
- เมื่อนำขึ้น IIS ให้คัดลอก `manifest.php`, `sw.js`, `offline.html`, `assets/js/pwa.js` และไอคอน `assets/images/pwa-*.png` ไปพร้อมโปรเจกต์ และให้ PHP handler ทำงานกับ `manifest.php`
- แอปที่เปิดจากไอคอนเริ่มที่หน้าเริ่มต้นของระบบ ไม่ผูกกับ QR หรือเลขบิลที่เคยสแกน ลูกค้ายังใช้ QR เพื่อเข้าบิลที่ถูกต้อง

ปุ่ม TH/EN เปลี่ยนเฉพาะข้อความของเว็บไซต์และจำภาษาที่เลือกในเบราว์เซอร์ ชื่ออาหาร หมวดหมู่ รายละเอียดจาก POS และหมายเหตุที่ลูกค้ากรอกยังแสดงตามข้อมูลต้นฉบับ แก้คำแปล UI ได้ใน `public/assets/js/translations.js`

## Build Tailwind

ใช้ Tailwind CSS CLI **4.1.18** จาก official release ที่ https://github.com/tailwindlabs/tailwindcss/releases/tag/v4.1.18 หรือแพ็กเกจ `@tailwindcss/cli` เวอร์ชันเดียวกัน

```powershell
& './storage/development/tools/tailwindcss.exe' -i './public/assets/css/tailwind-input.css' -o './public/assets/css/tailwind.css' --minify
```

`storage/development/tools/` เป็น build tool เฉพาะเครื่องที่ไม่ขึ้น Git ไฟล์ CSS ที่ build แล้วอยู่ในโปรเจกต์ ไม่ใช้ Tailwind Play CDN ในหน้าจริง

## สถาปัตยกรรมระบบเชื่อมต่อ SQL Server

- **การเชื่อมต่อ**: `app/Database/db.php` ดึงค่าจาก `.env` เชื่อมต่อผ่าน `ODBC Driver 18 for SQL Server` / `pdo_odbc`
- **การจัดการภาษาไทย**: SQL Server เก็บข้อมูลด้วย Collation `Thai_CI_AS` ข้อมูล `nvarchar` ถูกแปลงผ่าน Hex / Binary เพื่อให้ตัวอักษรภาษาไทยแสดงผลและบันทึกลงฐานข้อมูลได้อย่างแม่นยำ ไม่สูญหายเป็นเครื่องหมายคำถาม (`?`)
- **ความปลอดภัย**: Transaction แบบ ACID ใน `public/api/order.php` ควบคุมการเปิดบิล บันทึกรายการ และอัปเดตยอดเงินให้อยู่ใน Transaction เดียวกันเสมอ พร้อม Rollback หากมีข้อผิดพลาด

PHP built-in server และ `config/router.php` ใช้พัฒนาเท่านั้น หากนำไปใช้กับ IIS/Apache/Nginx ต้องตั้ง document root/access rules เพื่อไม่เปิดเผย config, .env, logs และไฟล์ภายใน รวมถึงทดสอบระบบฐานข้อมูลก่อนเปิดรับออเดอร์จริง

## แหล่งไฟล์

- Alpine.js 3.14.9 — https://alpinejs.dev (MIT)
- SweetAlert2 11.22.2 — https://sweetalert2.github.io (MIT)
- Tailwind CSS 4.1.18 — https://tailwindcss.com (MIT)
- Noto Sans Thai — Google Fonts / SIL Open Font License
- ภาพอาหารตัวอย่างจาก Unsplash: `photo-1559314809-0d155014e29e`, `photo-1562565652-a0d8f0c59eb4`, `photo-1569718212165-3a8278d5f624`, `photo-1546069901-ba9599a7e63c`, `photo-1544145945-f90425340c7e`, `photo-1570197788417-0e82375c9371`

โปรดแทนที่ภาพ ชื่อ ราคา และข้อมูลส่วนผสมด้วยข้อมูลจริงของร้านก่อนใช้งาน
# ตะกร้าและการส่งเข้าครัว

รายการในตะกร้าเก็บใน Session Storage แยกตามแท็บและบิล โดยยังไม่สร้างแถวใน `Fnt_Detail` เมื่อยืนยันสั่งจึงสร้างแถวที่มี `Food_Que_STS = 'S'` พร้อมปรับยอดและตัดสต็อก การรีเฟรชแท็บเดิมยังคงตะกร้า แต่การปิดแท็บอาจทำให้ตะกร้าหาย

ตะกร้าแบบเดิมที่บันทึกไว้ในฐานข้อมูลจะย้ายกลับมา Session Storage เมื่อเปิดบิลจากอุปกรณ์เจ้าของตะกร้า โดยบันทึกสำเนาในแท็บก่อนลบเฉพาะแถวรอของอุปกรณ์นั้น ตาราง `Web_Cart_Lines` และ API เดิมยังคงไว้เพื่อรองรับการย้ายและอุปกรณ์ที่ยังไม่รีเฟรช ห้ามลบแถวรอของอุปกรณ์อื่นแบบเหมารวม

แอปไม่นับรายการสถานะว่างในยอดที่คำนวณและหน้ารายการที่สั่ง ทั้งนี้ควรตรวจสอบฝั่ง POS และรายงานอื่นที่อ่าน `Fnt_Detail` หรือ `V_Fnt_Detail` ว่าไม่นับรายการสถานะว่างก่อนยืนยันเช่นกัน

## สถานะบิลยกเลิกและสต็อก

เมื่อ POS เปลี่ยน `Fnt_Header.Doc_Sts` เป็น 3 เว็บจะแสดงว่าบิลถูกยกเลิกและไม่อนุญาตให้สั่งเพิ่ม เว็บไม่คืนสต็อกเมื่อยกเลิกบิล สินค้าและวัตถุดิบยังถูกตัดตามปกติเมื่อสั่งอาหาร และบันทึกการตัดไว้ใน `Web_Stock_Deductions`

สำหรับฐานข้อมูลที่เคยติดตั้ง trigger คืนสต็อกของเว็บ ให้ตรวจและใช้ `database/migrations/003_disable_web_stock_return.sql` เพื่อปิด trigger เฉพาะตัวนั้น โดยไม่ลบรายการเก่า และไม่กระทบ trigger อื่นของ POS

ไฟล์ SQL ที่จำเป็นอยู่ใน `database/migrations/` ให้ตรวจสถานะตารางและสำรองฐานข้อมูลก่อนติดตั้ง โดยทดสอบบนฐานข้อมูลแยกก่อน ห้ามรันซ้ำเมื่อมีตารางหรือ trigger อยู่แล้ว

# ทดสอบด้วย Pest

ติดตั้ง dependencies สำหรับการพัฒนาโดยใช้ `composer install` แล้วรัน `composer test` ชุดทดสอบ API สร้างสำเนาไฟล์ชั่วคราวโดยไม่คัดลอก `.env` และปิดการเชื่อมต่อฐานข้อมูล จึงไม่เขียนข้อมูลลง POS จริง

ชุดนี้ตรวจสิทธิ์ วิธีเรียก API และกรณีข้อมูลจำเป็นขาดหรือฐานข้อมูลไม่พร้อม ยังไม่ครอบคลุมการเข้าสู่ระบบสำเร็จ การเปิดบิล การสั่งอาหาร หรือการตัดสต็อกจริง ซึ่งต้องใช้ฐานข้อมูล SQL Server สำหรับทดสอบที่แยกจากระบบร้าน

# ตรวจโค้ดและดู log

รัน `composer analyse` เพื่อตรวจโค้ด PHP ด้วย PHPStan (ระดับ 0) และ `composer test` เพื่อรัน Pest ระบบใช้ Monolog ส่งข้อผิดพลาดไปยัง PHP error log ซึ่งกำหนดตำแหน่งใน `php.ini` ของ IIS; ต้องติดตั้ง dependencies ด้วย `composer install --no-dev` บนเซิร์ฟเวอร์ หากยังไม่มี vendor ระบบจะใช้ PHP error log โดยตรง

# ตั้งค่าการเข้าสู่ระบบ

ติดตั้ง production dependencies ด้วย `composer install --no-dev` และให้ IIS สามารถเขียนโฟลเดอร์ `storage/rate-limit` ได้ ระบบจำกัดการลองเข้าสู่ระบบ 20 ครั้งต่อ IP ในช่วง 5 นาที และรีเซ็ตเมื่อล็อกอินสำเร็จ เครื่องที่แชร์ IP เดียวกันจะใช้โควตาร่วมกัน หากใช้หลายเครื่องเซิร์ฟเวอร์ ต้องตั้งค่าที่เก็บและ lock แบบใช้ร่วมกันก่อนเพื่อให้ข้อจำกัดมีผลครอบคลุมทุกเครื่อง
