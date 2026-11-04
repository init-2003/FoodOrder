# แนวทางพัฒนา QR Self-Ordering

เอกสารสำหรับผู้พัฒนาและ coding agent ที่ทำงานในโปรเจกต์นี้ อ่านร่วมกับ [README.md](README.md) สำหรับการรันระบบ และ [design.md](design.md) สำหรับข้อกำหนด UI

## 1. เป้าหมายและสถานะการเชื่อมต่อ

ระบบรองรับทั้ง **โหมดเชื่อมต่อจริงกับฐานข้อมูล SQL Server ของระบบ POS (POS_MODE=live)** และ **โหมดทดลอง (POS_MODE=demo)**:

- **ฐานข้อมูลจริง (Live SQL Server)**:
  - เชื่อมต่อกับ Microsoft SQL Server 2019 ที่ IP `192.168.2.4`, ฐานข้อมูล `DBFood`, บัญชี `sa`
  - ไดรเวอร์: `ODBC Driver 18 for SQL Server` ผ่าน PHP `pdo_odbc`
  - การจัดการภาษาไทย: เนื่องจาก Collation เป็น `Thai_CI_AS` และ ODBC บน Windows อาจแปลง `nvarchar` เป็น code page ANSI จึงใช้วิธี `CONVERT(varbinary(max), column)` แล้วถอดรหัส `UTF-16LE -> UTF-8` ด้วย `Database::decodeThai()` และส่งข้อความกลับด้วย `Database::hexThai()` เพื่อความถูกต้อง 100%
  - ตารางที่เชื่อมต่อ:
    - `Type`: หมวดหมู่อาหาร (10 หมวดหมู่)
    - `Trade`: รายการอาหารและราคาจริง (53 รายการ) โดยดึงข้อมูล:
      - `Trade_ID`: รหัสสินค้า/เมนู (เช่น `0010001` ถึง `0100003`)
      - `Trade_Name`: ชื่ออาหารภาษาไทย (แปลงผ่าน `varbinary -> UTF-16LE -> UTF-8`)
      - `Type_Name`: ชื่อหมวดหมู่อาหาร
      - `Unit_Name`: หน่วยนับ (จาน, ถ้วย, แก้ว)
      - `Sale_Price`: ราคาขายจริงในระบบ POS
      - `Cost_Price`: ราคาทุน
      - `Food_Type`: ประเภท (`FOOD`, `DRINK`)
      - `Link_Part_image`: ลิงก์รูปภาพ (หากกำหนดไว้ในตาราง)
    - `PBM_Table`: สถานะโต๊ะ (`Table_STS = 'O'` เมื่อเปิดบิล)
    - `PBM_CTRL`: รันเลขที่เอกสารบิลอัตโนมัติ (`PB_RUN_CD = 'FIN'`)
    - `Fnt_Header`: ข้อมูลหัวบิล ยอดรวม และภาษีมูลค่าเพิ่ม (VAT 7%)
    - `Fnt_Detail`: บันทึกรายการอาหารเข้าบิล (`Print_STS = ''`, `Food_Que_STS = ''`, `Trade_Point`)
    - `Movement`: บันทึกประวัติการตัดสต็อกสินค้า (`Reference = 'ขาย-web'`, `Pay_Qty`, `Balance_Qty`)
- **การตัดสต็อกสินค้า (`Trade` & `Movement`)**:
  - เมื่อสั่งอาหาร ระบบจะตัดจำนวนในตาราง `Trade` ทันที:
    `UPDATE Trade SET Qty = Qty - '$Qty_Cut_Stock' WHERE (Trade_ID = '$Trade_ID') OR (Group_Pro = '$Group_Pro' AND Group_Pro <> '')`
  - หากมีการตัดสต็อก (`rowCount > 0`) จะบันทึกการเคลื่อนไหวลงตาราง `Movement`:
    `INSERT INTO Movement (Branch_ID, Mo_Doc_Date, Mo_Doc_no, Reference, Trade_id, Pay_Qty, Cost_Price, Pb_User, Pb_now, Balance_Qty) VALUES ('001', '$datetime', '$docNo', 'ขาย-web', '$tradeId', '$qtyCut', '$costPrice', '$user', '$datetime', $balanceQty)`
  - **การตัดวัตถุดิบ (`Product_Ingredient`)**:
    - ค้นหาวัตถุดิบจาก `Product_Ingredient WHERE (STS_RAW = '' OR STS_RAW = 'Y') AND Trade_ID = ? ORDER BY autoid`
    - คำนวณ `$QtyRaw = $amountno * $Qty_Cut_Stock_Raw`
    - ตัดยอดวัตถุดิบใน `Trade`: `UPDATE Trade SET Qty = Qty - '$QtyRaw' WHERE Branch_Id = '001' AND Trade_ID = '$Trade_ID_Raw'`
    - อ่านยอดคงเหลือล่าสุด `$Balance_Qty`
    - บันทึกการตัดวัตถุดิบลง `Movement`:
      `INSERT INTO Movement (Branch_Id, Mo_Doc_Date, Mo_Doc_no, Reference, Trade_id, Pay_Qty, Cost_Price, Sale_Price, Balance_Qty, Pb_User, Pb_now) VALUES ('001', '$datetime', '$docNo', 'ตัดRAW-web', '$Trade_ID_Raw', '$QtyRaw', '$Cost_Price', '$Sale_Price', '$Balance_Qty', '$user', '$datetime')`
- **โหมดทดลอง (Demo Mode)**:
  - ตั้งค่า `POS_MODE=demo` ในไฟล์ `.env` เพื่อสลับกลับไปใช้ข้อมูลจำลองในเครื่องเมื่อไม่มีการเชื่อมต่อเครือข่าย POS

- **การตรวจสอบบิลเมื่อสแกน QR Code (`Fnt_Header`)**:
  - เมื่อลูกค้าสแกน QR Code ระบบจะส่งพารามิเตอร์ `Fn_Doc_No` (หรือ `doc`), `table`, และ `Branch_ID` (ค่าเริ่มต้น `001`)
  - ตรวจสอบสถานะบิลผ่านคำสั่ง:
    `SELECT * FROM Fnt_Header b WHERE b.Fn_Doc_No = '$Fn_Doc_No' AND b.Branch_ID = '001'`
  - ความหมายของ `Doc_Sts`:
    - `Doc_Sts = 0` : **เปิดบิล** ลูกค้าสามารถเลือกเมนูและสั่งอาหารเข้าครัวได้
    - `Doc_Sts = 1` : **ออกใบเสร็จแล้ว** บิลถูกปิด/ชำระเงินแล้ว ไม่สามารถสั่งเพิ่มได้ นำทางไปยังหน้าปิดบิล และแสดงประวัติรายการที่สั่ง
    - `Doc_Sts = 3` : **ยกเลิก** บิลถูกยกเลิกในระบบ POS ไม่สามารถสั่งได้ พร้อมแจ้งเตือนให้ติดต่อพนักงาน
  - หากไม่มีการระบุ `Fn_Doc_No` ใน URL (เข้าผ่าน `?table=...`) ระบบจะค้นหาบิลเปิดล่าสุด (`Doc_Sts = 0`) ของโต๊ะนั้นแทน

- รองรับพารามิเตอร์ทั้งผ่าน `?Fn_Doc_No=...` (หรือ `?doc=...`) และ `?table=...` โดยอัตโนมัติ

## 2. สแตกและโครงสร้าง

ใช้ PHP 8.5, HTML5, Tailwind CSS, Vanilla JavaScript, Alpine.js และ SweetAlert2 ตามสแตกที่ผู้ใช้กำหนด การเชื่อมฐานข้อมูลในอนาคตใช้ SQL Server ผ่าน PHP PDO_SQLSRV และ Composer จัดการ PHP packages

| ตำแหน่ง | หน้าที่และแนวทางแก้ไข |
| --- | --- |
| `index.php` | Entry point กำหนดหน้าเริ่มต้นและเรียก UI หลัก |
| `includes/app.php` | HTML, Alpine bindings, SVG icon symbols และโครงหน้าทั้งหมด |
| `pages/*.php` | Entry point เมนู ตะกร้า ประวัติ และหน้าปิดบิล ใช้ UI หลักร่วมกัน |
| `assets/js/app.js` | `orderingApp`, การนำทาง, ตะกร้า, ประวัติ และ `DemoStore` |
| `assets/data/menu.json` | หมวดหมู่และเมนูตัวอย่าง |
| `assets/css/app.css` | Design tokens, component styles และ responsive rules |
| `assets/css/tailwind-input.css` | Source และขอบเขตการ scan ของ Tailwind |
| `assets/css/tailwind.css` | ไฟล์ที่ build แล้ว ห้ามแก้แทน source โดยตรง |
| `api/` | เมนูตัวอย่างและ endpoint ที่เตรียมไว้สำหรับการเชื่อมจริง |
| `includes/db.php` | คลาส `Database` จัดการ PDO_ODBC connection และแปลงตัวอักษรภาษาไทย |
| `config/` | การโหลดคอนฟิกฐานข้อมูลและ POS |
| `includes/functions.php` | JSON response helper |
| `includes/security.php` | HTTP response headers เบื้องต้น ไม่ใช่ระบบ authentication |
| `router.php` | Router ของ PHP development server และกฎปิดการเข้าถึงไฟล์ภายใน |
| `assets/vendor/`, `assets/fonts/` | Libraries และฟอนต์ในโปรเจกต์ พร้อมใบอนุญาตใน `assets/vendor/licenses/` |

คงสแตกเดิมและใช้ component ที่มีอยู่เมื่อเหมาะสม ไม่เพิ่ม framework หรือระบบ build ใหม่เพียงเพื่อแก้งาน UI เล็กน้อย

## 3. สัญญาข้อมูลและพฤติกรรมที่ต้องรักษา

### เมนูและ API

- `GET api/menu.php` คืน `{ mode, categories, items }` จากตาราง `Type` และ `Trade` ใน SQL Server (หรือ fallback เป็น JSON เมื่อเป็นโหมด demo)
- หมวดหมู่ใช้ `id`, `name`, `icon`
- เมนูใช้ `id`, `name`, `english`, `category`, `price`, `image`, `description`, `recommended`, `badge`, `available`, `allergens`
- `GET api/bill.php?table={id}` ตรวจสอบสถานะบิลเปิด (`Doc_Sts = '0'`) ใน `Fnt_Header` สำหรับโต๊ะนั้น
- `GET api/order-history.php?table={id}` ดึงรายการที่สั่งแล้วจริงในบิลจากตาราง `Fnt_Detail` จัดกลุ่มตามช่วงเวลาการสั่ง
- `POST api/order.php` รับรายการอาหาร เปิดบิลใหม่ผ่าน `PBM_CTRL` (ถ้ายังไม่มีบิลเปิด) เพิ่มรายการลง `Fnt_Detail` (`Print_STS = '1'`, `Food_Que_STS = 'P'`), อัปเดตยอดรวมและ VAT ใน `Fnt_Header` และปรับสถานะโต๊ะใน `PBM_Table`

### ตะกร้าและประวัติ

- `PosStore.placeOrder()` ส่งรายการผ่าน `POST api/order.php` เข้าฐานข้อมูล SQL Server
- เมนูเดียวกันแต่หมายเหตุต่างกันต้องแยกบรรทัด เมนูและหมายเหตุเหมือนกันรวมจำนวนได้เมื่อไม่เกินขีดจำกัด
- จำนวนต่อบรรทัดอยู่ในช่วง 1–99; ลดจำนวนในตะกร้าจนเหลือศูนย์เป็นการลบรายการ
- หมายเหตุจำกัด 200 ตัวอักษร
- ปิดปุ่มส่งและการแก้ตะกร้าระหว่างกำลังบันทึก เก็บตะกร้าไว้เมื่อการบันทึกไม่สำเร็จ
- ล้างตะกร้าเมื่อบันทึกสำเร็จ อัปเดตประวัติจากฐานข้อมูล และอนุญาตให้สั่งรอบถัดไป
- เมื่อบิลปิด ห้ามเพิ่มหรือส่งอาหารใหม่ แต่ยังดูประวัติได้
- แสดงเวลาเป็น `Asia/Bangkok` และราคาเป็นบาท

localStorage แยกตาม origin และ browser profile ไม่ใช่ฐานข้อมูลร่วมข้ามเครื่อง ตะกร้าในรุ่นนี้ใช้ key เดียวกันในแท็บของ origin เดียวกัน ส่วนประวัติและสถานะบิลรับการเปลี่ยนแปลงผ่าน `storage` event จึงไม่ควรอ้างว่าตะกร้าแยกอิสระต่อแท็บหรือรับประกัน concurrent orders

### การนำทาง

- ใช้ hash route `#menu`, `#cart`, `#history`, `#closed` และ entry point ใน `pages/`
- `pages/closed.php` มีผลจำลองปิดบิลใน browser; `#closed` เป็นเส้นทางหน้าจอ ไม่ใช่กลไกปิดบิลจริงของ POS
- ปุ่มรีเซ็ตต้องระบุว่าจะล้างข้อมูลทดลองและยืนยันก่อนล้าง
- ระวัง `dirname()` บน Windows คืน backslash ต้อง normalize URL base เป็น forward slash

## 4. แนวทางเขียนโค้ด

- ใช้ UTF-8 และข้อความผู้ใช้ภาษาไทย รักษาชื่อภาษาอังกฤษสำหรับ identifiers
- PHP ใหม่ใช้ `declare(strict_types=1)` และ JSON response รูปแบบสม่ำเสมอ
- แสดงข้อมูลจากเมนู/หมายเหตุด้วย `x-text` หรือ escape ตามบริบท ไม่แทรกข้อมูลผู้ใช้ด้วย `x-html` หรือ `innerHTML`
- แยก data access จาก UI ผ่าน store/service เพื่อเปลี่ยนจาก demo เป็น API ได้โดยไม่เขียนหน้าจอใหม่
- คงไฟล์ภาพ ฟอนต์ และ runtime libraries ไว้ในโปรเจกต์ ห้ามนำ CDN runtime มาเป็นเงื่อนไขในการเปิดหน้าเว็บโดยไม่จำเป็น
- ไม่แก้ไฟล์ vendor/minified ด้วยมือ อัปเดตเวอร์ชันและใบอนุญาตให้สอดคล้องกันเมื่อเปลี่ยน dependency
- แก้ CSS ใน source และตรวจ cascade เดิม โดยเฉพาะ media queries ที่มี override ท้ายไฟล์
- อย่าเพิ่มข้อมูลลับลง source, log, URL หรือเอกสาร ใช้ placeholder ใน `.env.example`
- ไม่เปลี่ยน schema หรือเขียนข้อมูลเข้า POS จริงจากงาน frontend ที่ยังอยู่ในโหมดทดลอง
- แก้เฉพาะส่วนที่เกี่ยวข้อง รักษางานเดิมของผู้ใช้ และอัปเดตเอกสารเมื่อพฤติกรรมหรือวิธีรันเปลี่ยน

## 5. การรันและตรวจงาน

รันจาก root ของโปรเจกต์ใน PowerShell:

```powershell
& 'C:\php\php.exe' -S 127.0.0.1:8080 router.php
```

ถ้า PHP อยู่ใน PATH ใช้ `php -S 127.0.0.1:8080 router.php` หรือ `composer serve` ได้ พอร์ต 8080 เป็นตัวอย่าง; ตรวจเซิร์ฟเวอร์เดิมก่อนเปิดเพิ่มและไม่หยุด process ที่ไม่ใช่ของงานนี้

เมื่อเปลี่ยน Tailwind classes ให้ build ใหม่ด้วย CLI 4.1.18 หากมีเครื่องมือใน `tools/`:

```powershell
& './tools/tailwindcss.exe' -i './assets/css/tailwind-input.css' -o './assets/css/tailwind.css' --minify
```

`tools/` ไม่ได้เป็น dependency ที่ติดมากับทุก checkout ดูวิธีเตรียมเครื่องมือใน README ก่อนใช้ ไม่จำเป็นต้อง build Tailwind เมื่อแก้เฉพาะเอกสารหรือ `app.css`

ตรวจ PHP เฉพาะไฟล์ที่เปลี่ยนด้วย `php -l <file>` และตรวจ flow ที่ได้รับผลกระทบ งานเอกสารล้วนตรวจความถูกต้องและลิงก์ก็เพียงพอ โปรเจกต์ยังไม่มี automated test suite ที่ตั้งค่าไว้

สำหรับการเปลี่ยนหน้าจอหรือ ordering flow ให้ตรวจตามความเกี่ยวข้อง:

1. เมนูโหลดสำเร็จ ค้นหาไทย/อังกฤษ เลือกหมวดหมู่ และค้นหาไม่พบ
2. เพิ่มอาหาร แก้หมายเหตุ เพิ่ม–ลดจำนวน ลบรายการ และอาหารหมด
3. ยืนยัน/ยกเลิกการสั่ง ป้องกันกดซ้ำ และสั่งเพิ่มรอบใหม่
4. ตะกร้า/ประวัติหลังรีเฟรช การแสดงยอดรวม และเวลาประวัติ
5. ปิดบิลแล้วสั่งไม่ได้ ดูประวัติได้ และรีเซ็ตตัวอย่างได้
6. Responsive ที่ 320, 390, ช่วง 760/761, 1230/1231 และจอ 1440 ตามส่วนที่เปลี่ยน
7. ไม่ล้นแนวนอน ปุ่มล่างไม่บังเนื้อหา focus/keyboard ใช้งานได้ และไม่มี console errors
8. เข้าผ่าน root กับ `pages/*.php` แล้ว asset paths ยังถูกต้อง

รายงานสิ่งที่เปลี่ยน สิ่งที่ตรวจจริง และข้อจำกัดที่ยังเหลือ อย่ารายงานผลทดสอบที่ไม่ได้รัน

## 6. ขั้นเชื่อม SQL Server ในอนาคต

ส่วนนี้เป็นข้อกำหนดของระยะถัดไป ไม่ใช่ความสามารถที่มีแล้ว:

- ยืนยัน schema และกระบวนการรับออเดอร์/ส่งเข้าครัวของ POS ก่อนออกแบบคำสั่ง SQL
- POS เป็นผู้สร้างบิล ควบคุมสถานะปิดบิล และเป็นแหล่งอ้างอิงราคา ภาษี ส่วนลด และยอดรวม
- QR token ต้องผูกกับบิลเฉพาะครั้ง ไม่ใช้หมายเลขโต๊ะหรือ Bill ID ที่เดาได้เป็นสิทธิ์เข้าถึงโดยลำพัง
- ตรวจสิทธิ์ สถานะบิล เมนู จำนวน และราคาฝั่งเซิร์ฟเวอร์ทุกครั้ง ไม่เชื่อยอดที่ browser ส่งมา
- ใช้ PDO prepared statements และ account ที่มีสิทธิ์เท่าที่จำเป็น
- ทำ transaction/locking ให้สอดคล้องกับขั้นตอนปิดบิลของ POS และใช้ idempotency ที่มี unique constraint หรือกลไกที่เทียบเท่าฝั่งเซิร์ฟเวอร์
- กรณี timeout ต้องตรวจผลคำขอเดิมก่อน retry เพื่อไม่สร้างรายการซ้ำ
- ประวัติและสถานะร่วมข้ามเครื่องต้องมาจากเซิร์ฟเวอร์ ตะกร้าที่ยังไม่ส่งเก็บแยกแต่ละเครื่องโดยผูกกับบิล
- `.env.example` ยังเป็นแบบฟอร์ม แอปไม่ได้โหลด `.env` อัตโนมัติ และ `config/pos.php` ยังใช้ค่า demo แบบคงที่
- ตั้งค่า HTTPS, session/authentication, CSRF และ access rules ของ production web server ก่อนใช้งานจริง ไม่ถือว่า development router เป็นระบบป้องกันของ IIS/Apache/Nginx
