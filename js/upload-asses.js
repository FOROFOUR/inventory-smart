// ── Tab Switching ─────────────────────────────────────────────────────────
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', () => {
        const targetTab = button.getAttribute('data-tab');
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
        button.classList.add('active');
        document.getElementById(`${targetTab}-panel`).classList.add('active');
    });
});

// ── Category Change Handler — fetches from DB (no more hardcoded list) ────
document.getElementById('category').addEventListener('change', function () {
    const categoryId   = this.value;
    const categoryText = this.options[this.selectedIndex].text;
    const subSelect    = document.getElementById('sub_category');
    const othersSubField = document.getElementById('others_sub_field');
    const othersCatField = document.getElementById('others_cat_field');

    // Show/hide "New Category" input if "Others" selected
    if (othersCatField) {
        othersCatField.style.display = (categoryText === 'Others') ? 'block' : 'none';
    }

    // Reset sub-category dropdown
    subSelect.innerHTML = '<option value="">Loading...</option>';
    subSelect.disabled  = true;
    if (othersSubField) othersSubField.style.display = 'none';

    if (!categoryId) {
        subSelect.innerHTML = '<option value="">Select category first</option>';
        return;
    }

    // Fetch sub-categories from the database
    fetch(`get-subcategories.php?category_id=${encodeURIComponent(categoryId)}`)
        .then(res => {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(data => {
            subSelect.innerHTML = '<option value="">Select sub-category</option>';

            if (data && data.length > 0) {
                data.forEach(sub => {
                    const option       = document.createElement('option');
                    option.value       = sub.id;
                    option.textContent = sub.name;
                    subSelect.appendChild(option);
                });
                subSelect.disabled = false;
            } else {
                subSelect.innerHTML = '<option value="">No sub-categories found</option>';
            }
        })
        .catch(err => {
            console.error('Failed to load sub-categories:', err);
            subSelect.innerHTML = '<option value="">Error loading sub-categories</option>';
        });
});

// ── Sub-category "Others" handler ─────────────────────────────────────────
document.getElementById('sub_category').addEventListener('change', function () {
    const othersSubField = document.getElementById('others_sub_field');
    if (!othersSubField) return;
    const selectedText = this.options[this.selectedIndex].text;
    othersSubField.style.display = (selectedText === 'Others') ? 'block' : 'none';
});

// ── Image Upload Handling (Max 3 images) ──────────────────────────────────
const imageInputs  = document.querySelectorAll('.image-input');
let uploadedImages = new Array(3).fill(null);

imageInputs.forEach(input => {
    const index      = parseInt(input.getAttribute('data-index'));
    const uploadBox  = input.closest('.upload-box');
    const removeBtn  = uploadBox.querySelector('.remove-image-btn');

    input.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            if (file.size > 5 * 1024 * 1024) {
                showNotification('Image size must be less than 5MB', 'error');
                input.value = '';
                return;
            }
            const reader   = new FileReader();
            reader.onload  = function (e) {
                const previewImg     = uploadBox.querySelector('.preview-image');
                const previewWrapper = uploadBox.querySelector('.image-preview-wrapper');
                const uploadContent  = uploadBox.querySelector('.upload-content');
                previewImg.src       = e.target.result;
                uploadContent.style.display  = 'none';
                previewWrapper.style.display = 'block';
                uploadBox.classList.add('has-image');
                uploadedImages[index] = file;
                if (index < 2) {
                    document.getElementById(`uploadBox${index + 2}`).classList.add('active');
                }
            };
            reader.readAsDataURL(file);
        } else {
            showNotification('Please select a valid image file', 'error');
            input.value = '';
        }
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const previewWrapper = uploadBox.querySelector('.image-preview-wrapper');
            const uploadContent  = uploadBox.querySelector('.upload-content');
            input.value                      = '';
            uploadedImages[index]            = null;
            previewWrapper.style.display     = 'none';
            uploadContent.style.display      = 'flex';
            uploadBox.classList.remove('has-image');
            for (let i = index + 1; i < 3; i++) {
                if (!uploadedImages[i]) {
                    document.getElementById(`uploadBox${i + 1}`).classList.remove('active');
                }
            }
        });
    }
});

// ── Manual Asset Form Submission ──────────────────────────────────────────
document.getElementById('manualAssetForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    uploadedImages.forEach(file => { if (file) formData.append('images[]', file); });

    try {
        const response = await fetch('api/add-asset.php', { method: 'POST', body: formData });
        const data     = await response.json();
        if (data.success) {
            showNotification('Asset added successfully!', 'success');
            this.reset();
            uploadedImages = new Array(3).fill(null);
            document.querySelectorAll('.upload-box').forEach((box, idx) => {
                box.querySelector('.image-preview-wrapper').style.display = 'none';
                box.querySelector('.upload-content').style.display        = 'flex';
                box.classList.remove('has-image');
                if (idx > 0) box.classList.remove('active');
            });
            const subSel = document.getElementById('sub_category');
            subSel.innerHTML = '<option value="">Select category first</option>';
            subSel.disabled  = true;
            updateHeaderStats();
        } else {
            showNotification(data.message || 'Error adding asset', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error adding asset. Please try again.', 'error');
    }
});

// ── Excel drag-and-drop ───────────────────────────────────────────────────
const dropZone = document.getElementById('dropZone');

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && /\.(xlsx|xls|csv)$/i.test(file.name)) {
        document.getElementById('excel_file').files = e.dataTransfer.files;
        document.getElementById('excel_file').dispatchEvent(new Event('change'));
    } else {
        showNotification('Please drop a valid Excel/CSV file', 'error');
    }
});

// ── Notification ──────────────────────────────────────────────────────────
function showNotification(message, type = 'info') {
    const n = document.createElement('div');
    n.style.cssText = `
        position:fixed; top:2rem; right:2rem;
        padding:1rem 1.5rem;
        background:${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6'};
        color:white; border-radius:10px;
        box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);
        z-index:10000; animation:slideIn 0.3s ease;
        font-size:0.9375rem; font-weight:500; max-width:400px;
    `;
    n.textContent = message;
    document.body.appendChild(n);
    setTimeout(() => { n.style.animation = 'slideOut 0.3s ease'; setTimeout(() => n.remove(), 300); }, 4000);
}

// ── Update header stats ───────────────────────────────────────────────────
async function updateHeaderStats() {
    try {
        const res  = await fetch('api/get-asset-count.php');
        const data = await res.json();
        if (data.success) {
            const el = document.querySelector('.stat-value');
            if (el) el.textContent = data.count.toLocaleString();
        }
    } catch (e) { console.error('Error updating stats:', e); }
}

// ── Animations ────────────────────────────────────────────────────────────
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn  { from { transform:translateX(400px); opacity:0; } to { transform:translateX(0);    opacity:1; } }
    @keyframes slideOut { from { transform:translateX(0);    opacity:1; } to { transform:translateX(400px); opacity:0; } }
`;
document.head.appendChild(style);