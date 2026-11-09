      <!-- 2. Table Selection Section (Main System UI Style) -->
      <section x-show="page === 'tables'" <?= ($initialPage !== 'tables') ? 'style="display: none;"' : '' ?> x-cloak class="tables-section page-animated" aria-label="เลือกโต๊ะ">
        <div class="tables-heading manage-heading">
          <div>
            <h1 data-i18n="เลือกโต๊ะ" x-text="t('เลือกโต๊ะ')">เลือกโต๊ะ</h1>
          </div>
        </div>
        <div class="table-category-sticky" :class="{'is-stuck': tableCategoryStuck}"><div class="category-list" :class="{'category-list-filtered': tableFilter !== 'all'}" role="group" :aria-label="t('กรองสถานะโต๊ะ')">
          <button type="button" class="category-button" x-show="!tablesLoading || tablesList.length" :class="{'selected': tableFilter === 'all'}" :aria-pressed="tableFilter === 'all'" @click="tableFilter = 'all'">
            <span x-text="t('ทั้งหมด') + ' (' + tablesList.length + ')'">ทั้งหมด</span>
          </button>
          <button type="button" class="category-button" x-show="!tablesLoading || tablesList.length" :class="{'selected': tableFilter === 'open'}" :aria-pressed="tableFilter === 'open'" @click="tableFilter = 'open'">
            <span class="status-dot is-open"></span>
            <span x-text="t('เปิดบิลแล้ว') + ' (' + tablesList.filter(t => t.isOpen).length + ')'">เปิดบิลแล้ว</span>
          </button>
          <button type="button" class="category-button" x-show="!tablesLoading || tablesList.length" :class="{'selected': tableFilter === 'available'}" :aria-pressed="tableFilter === 'available'" @click="tableFilter = 'available'">
            <span class="status-dot is-available"></span>
            <span x-text="t('ว่าง') + ' (' + tablesList.filter(t => !t.isOpen).length + ')'">ว่าง</span>
          </button>
          <button type="button" class="category-button topbar-switch-table-btn topbar-refresh" @click="refreshTables()" :disabled="tablesLoading || tablesRefreshing" :aria-label="t('รีเฟรชสถานะโต๊ะ')" :title="t('รีเฟรชสถานะโต๊ะ')">
            <svg class="icon" :class="{'is-refreshing': tablesRefreshing}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 0 0-14-5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14 5l2-2"/><path d="M16 16h4v4"/></svg>
          </button>
          <template x-if="tablesLoading && !tablesList.length">
            <div class="skeleton-categories" role="status" :aria-label="t('กำลังโหลด')">
              <span class="skeleton skeleton-category"></span>
              <span class="skeleton skeleton-category"></span>
              <span class="skeleton skeleton-category"></span>
            </div>
          </template>
        </div></div>

        <div class="tables-native-search">
          <label class="search-field tables-search-field">
            <svg class="icon"><use href="#i-search"/></svg>
            <input type="search" enterkeyhint="search" x-model.trim="tableSearch" :placeholder="t('พิมพ์หมายเลขโต๊ะ')" :aria-label="t('ค้นหาหมายเลขโต๊ะ')">
            <button type="button" class="tables-search-clear" x-show="tableSearch" @click="tableSearch = ''" :aria-label="t('ล้างคำค้น')" x-cloak><svg class="icon"><use href="#i-close"/></svg></button>
          </label>
        </div>

        <div class="food-grid skeleton-menu" x-show="tablesLoading && !tablesList.length" role="status" :aria-label="t('กำลังโหลด')">
          <template x-for="n in 6" :key="n">
            <div class="food-card skeleton-card" aria-hidden="true">
              <div class="skeleton skeleton-food-image"></div>
              <div class="food-card-body">
                <div class="skeleton skeleton-line skeleton-title"></div>
                <div class="skeleton skeleton-line skeleton-short"></div>
              </div>
            </div>
          </template>
        </div>

        <div class="food-grid tables-food-grid" x-show="(!tablesLoading || tablesList.length) && filteredTables.length > 0">
          <template x-for="(tbl, index) in filteredTables" :key="tbl.id">
            <article class="food-card table-card-item" :style="'--card-delay:' + (Math.min(index, 7) * 45) + 'ms'" @click="selectTable(tbl)">
              <div class="food-image-button table-card-visual">
                <div class="table-visual-surface" :class="tbl.isOpen ? 'surface-open' : 'surface-available'">
                  <strong class="table-visual-number" x-text="tbl.name"></strong>
                </div>
                <span class="food-badge" :class="{'green-badge': !tbl.isOpen}">
                  <span class="status-dot" :class="tbl.isOpen ? 'is-open' : 'is-available'"></span>
                  <span x-text="tbl.isOpen ? t('เปิดบิลแล้ว') : t('ว่าง')"></span>
                </span>
              </div>
              <div class="food-card-body">
                <p class="food-english" style="text-align: center; margin: 0;" x-text="tbl.isOpen && tbl.docNo ? (t('เลขที่บิล') + ' ' + tbl.docNo) : (t('เลขที่บิล') + ' N/A')"></p>
              </div>
            </article>
          </template>
        </div>

        <div class="empty-state search-empty" x-show="!tablesLoading && !filteredTables.length" x-cloak>
          <svg class="icon"><use href="#i-search"/></svg>
          <h3 x-text="t('ไม่พบหมายเลขโต๊ะที่ค้นหา')">ไม่พบหมายเลขโต๊ะที่ค้นหา</h3>
          <p x-text="t('ลองค้นหาด้วยหมายเลขอื่น หรือดูโต๊ะทั้งหมด')">ลองค้นหาด้วยหมายเลขอื่น หรือดูโต๊ะทั้งหมด</p>
          <button type="button" class="text-button" @click="tableSearch = ''; tableFilter = 'all'"><span x-text="t('แสดงโต๊ะทั้งหมด')">แสดงโต๊ะทั้งหมด</span> <span>→</span></button>
        </div>
      </section>
