EventManager.on('router:initialized', () => {
    RouterManager.register('/cars', {
        template: 'car/catalog.html',
        title: '{LNG_All vehicles}',
        requireAuth: true
    });

    RouterManager.register('/car-my-bookings', {
        template: 'car/my-bookings.html',
        title: '{LNG_My bookings}',
        requireAuth: true
    });

    RouterManager.register('/car-booking', {
        template: 'car/booking.html',
        title: '{LNG_Book a car}',
        requireAuth: true
    });

    RouterManager.register('/car-approvals', {
        template: 'car/approvals.html',
        title: '{LNG_Car approvals}',
        requireAuth: true
    });

    RouterManager.register('/car-review', {
        template: 'car/review.html',
        title: '{LNG_Review booking}',
        menuPath: '/car-approvals',
        requireAuth: true
    });

    RouterManager.register('/vehicles', {
        template: 'car/vehicles.html',
        title: '{LNG_Vehicles}',
        requireAuth: true
    });

    RouterManager.register('/car-settings', {
        template: 'car/settings.html',
        title: '{LNG_Settings}',
        requireAuth: true
    });

    RouterManager.register('/car-categories', {
        template: 'car/categories.html',
        title: '{LNG_Categories}',
        requireAuth: true
    });

    // ภาพของห้อง/รถ (catalog-detail) — โหลดหลัง main.js ทำงานแล้ว เพื่อติด ?v= ด้วย versionedUrl ของ main.js
    // (ไม่มี ?v= = service worker ให้ตัวที่เคยโหลดไว้ตลอดไป)
    Utils.dom.loadResources([
        'js/components/GallerySlideshow.js',
        'Now/css/galleryslideshow.css'
    ].map(versionedUrl));
});

function formatVehicleWithImage(cell, rawValue, rowData, attributes) {
    const opts = attributes.lookupOptions || attributes.tableDataOptions || attributes.tableFilterOptions;

    // Normalizer: build a map value->text
    const makeMap = (options) => {
        if (!options) return new Map();
        if (Array.isArray(options)) {
            // [{value,text}, ...]
            return new Map(options.map(o => [String(o.value), o.text]));
        }
        // object map {val: label, ...}
        return new Map(Object.entries(options).map(([k, v]) => [String(k), v]));
    };

    const map = makeMap(opts);

    const key = rawValue === null || rawValue === undefined ? '' : String(rawValue);
    const label = map.has(key) ? map.get(key) : (rawValue && rawValue.text) ? rawValue.text : key;

    const thumbHtml = rowData?.first_image_url
        ? `<span class="car-table-thumb"><img src="${Utils.string.escape(rowData.first_image_url)}" alt="${Utils.string.escape(label || 'Vehicle')}" loading="lazy"></span>`
        : '<span class="car-table-thumb car-table-thumb--placeholder icon-car" aria-hidden="true"></span>';

    cell.innerHTML =
        `<span class="car-table-cell">${thumbHtml}<strong>${Utils.string.escape(label || '-')}</strong></span>`;
}

function initCarSettings(element, data) {
    const approveLevel = element.querySelector('#car_approve_level');
    if (!approveLevel) {
        return () => {};
    }

    const approveChange = () => {
        element.querySelectorAll('.can-approve').forEach(el => {
            const level = parseInt(el.dataset.level || '0', 10);
            el.style.display = level > 0 && level <= parseInt(approveLevel.value || '0', 10) ? 'flex' : 'none';
        });
    };
    approveLevel.addEventListener('change', approveChange);
    approveChange();

    return () => {
        approveLevel.removeEventListener('change', approveChange);
    };
}
