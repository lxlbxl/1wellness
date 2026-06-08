// Admin SPA Logic
let adminData = null;

// Initialize Lucide
lucide.createIcons();

// 1. Initial Auth Check & Data Fetch
async function initialize() {
    await fetchData();
    document.getElementById('loader').classList.add('hidden');
    document.getElementById('app').classList.remove('hidden');
}

async function fetchData(action = 'dashboard') {
    const filters = getFilters(action);
    let url = `api/admin_data.php?action=${action}`;

    if (action === 'assessments') {
        if (filters.funnel) url += `&funnel=${filters.funnel}`;
        if (filters.status) url += `&status=${filters.status}`;
        if (filters.search) url += `&search=${encodeURIComponent(filters.search)}`;
    } else if (action === 'sales') {
        if (filters.status) url += `&status=${filters.status}`;
        if (filters.search) url += `&search=${encodeURIComponent(filters.search)}`;
    } else if (action === 'tracking') {
        if (filters.funnel) url += `&funnel=${filters.funnel}`;
        if (filters.event) url += `&event=${filters.event}`;
    } else if (action === 'users') {
        if (filters.search) url += `&search=${encodeURIComponent(filters.search)}`;
    }

    try {
        const response = await fetch(url);
        if (response.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        const data = await response.json();
        if (data.success) {
            if (action === 'dashboard') {
                adminData = data;
                renderDashboard();
            } else if (action === 'assessments') {
                renderAssessments(data.data);
            } else if (action === 'sales') {
                renderSales(data);
            } else if (action === 'tracking') {
                renderTracking(data.data);
            } else if (action === 'users') {
                renderUsers(data.data);
            } else if (action === 'audit') {
                renderAudit(data.data);
            }
        }
    } catch (err) {
        console.error('Fetch error:', err);
    }
}

function getFilters(action) {
    if (action === 'assessments') {
        return {
            funnel: document.getElementById('filter-funnel')?.value || '',
            status: document.getElementById('filter-status')?.value || '',
            search: document.getElementById('filter-search')?.value || ''
        };
    } else if (action === 'sales') {
        return {
            status: document.getElementById('sales-status')?.value || '',
            search: document.getElementById('sales-search')?.value || ''
        };
    } else if (action === 'tracking') {
        return {
            funnel: document.getElementById('tracking-funnel')?.value || '',
            event: document.getElementById('tracking-event')?.value || ''
        };
    } else if (action === 'users') {
        return {
            search: document.getElementById('users-search')?.value || ''
        };
    }
    return {};
}

// 2. View Management
function switchView(viewId) {
    document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
    const targetView = document.getElementById(viewId);
    if (targetView) {
        targetView.classList.add('active');
    }

    document.querySelectorAll('.nav-link, .mobile-nav-link').forEach(link => {
        if (link.dataset.view === viewId) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    if (viewId === 'dashboard') fetchData('dashboard');
    if (viewId === 'assessments') fetchData('assessments');
    if (viewId === 'sales') fetchData('sales');
    if (viewId === 'tracking') fetchData('tracking');
    if (viewId === 'users') fetchData('users');
    if (viewId === 'audit') fetchData('audit');
}

async function viewDetails(id) {
    try {
        const response = await fetch(`api/admin_data.php?action=assessment_details&id=${id}`);
        const data = await response.json();
        if (data.success) {
            showDetailModal(data.assessment);
        }
    } catch (err) {
        console.error('Error fetching details:', err);
    }
}

async function deleteAssessment(id) {
    if (!confirm('Are you sure you want to delete this assessment?')) return;
    try {
        const response = await fetch('api/admin_data.php?action=delete_assessment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        });
        const data = await response.json();
        if (data.success) {
            fetchData('assessments');
        }
    } catch (err) {
        console.error('Delete failed:', err);
    }
}

function renderAssessments(data) {
    const container = document.getElementById('assessments-list');
    if (!container) return;

    if (data.length === 0) {
        container.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-sage-400">No assessments found matching filters.</td></tr>`;
        return;
    }

    container.innerHTML = data.map(item => `
        <tr class="hover:bg-sage-50/50 transition-colors group">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-sage-50 flex items-center justify-center text-sage-500 font-bold">
                        ${(item.name || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-sage-900">${item.name || 'Anonymous'}</div>
                        <div class="text-xs text-sage-400">${item.email || 'No Email'}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${getTypeStyle(item.assessment_type)}">
                    ${item.assessment_type || 'General'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${getStatusStyle(item.status)}">
                    ${item.status || 'Completed'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-xs text-sage-400">
                ${new Date(item.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <div class="flex justify-end gap-1">
                    <button onclick="viewDetails('${item.id}')" class="p-2 text-sage-400 hover:text-sage-600 transition-colors">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="deleteAssessment('${item.id}')" class="p-2 text-sage-400 hover:text-red-500 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    lucide.createIcons();
}

function renderUsers(data) {
    const container = document.getElementById('users-list');
    if (!container) return;

    if (data.length === 0) {
        container.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-sage-400">No members found.</td></tr>`;
        return;
    }

    container.innerHTML = data.map(user => `
        <tr class="hover:bg-sage-50/50 transition-colors group">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-sage-500/10 flex items-center justify-center text-sage-500 font-bold">
                        ${(user.name || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-sage-900">${user.name || 'Anonymous'}</div>
                        <div class="text-[10px] text-sage-400 font-mono uppercase tracking-tighter">ID: ${user.id}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-sage-700">${user.email}</div>
                <div class="text-xs text-sage-400">${user.phone || 'No Phone'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-xs text-sage-400 font-medium">
                ${new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <div class="flex justify-end gap-1">
                    <button onclick="editUser('${user.id}')" class="p-2 text-sage-400 hover:text-sage-600 transition-colors">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </button>
                    <button onclick="deleteUser('${user.id}')" class="p-2 text-sage-400 hover:text-red-500 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    lucide.createIcons();
}

async function editUser(id) {
    try {
        const response = await fetch(`api/admin_data.php?action=user_details&id=${id}`);
        const data = await response.json();
        if (data.success) {
            const user = data.user;
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-name').value = user.name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-phone').value = user.phone || '';
            document.getElementById('user-modal-title').textContent = 'Edit Member';
            openUserModal();
        }
    } catch (err) {
        console.error('Error fetching user:', err);
    }
}

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this member? This action is permanent.')) return;
    try {
        const response = await fetch('api/admin_data.php?action=delete_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        });
        const data = await response.json();
        if (data.success) {
            fetchData('users');
        }
    } catch (err) {
        console.error('Delete failed:', err);
    }
}

function openUserModal() {
    const modal = document.getElementById('user-modal');
    const container = document.getElementById('user-modal-container');
    modal.classList.remove('invisible');
    modal.classList.add('opacity-100');
    container.classList.remove('translate-x-full');
}

function closeUserModal() {
    const modal = document.getElementById('user-modal');
    const container = document.getElementById('user-modal-container');
    container.classList.add('translate-x-full');
    modal.classList.remove('opacity-100');
    setTimeout(() => {
        modal.classList.add('invisible');
    }, 300);
}

async function saveUser() {
    const id = document.getElementById('user-id').value;
    const name = document.getElementById('user-name').value;
    const email = document.getElementById('user-email').value;
    const phone = document.getElementById('user-phone').value;

    if (!name || !email) return alert('Name and Email are required');

    try {
        const response = await fetch('api/admin_data.php?action=update_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}`
        });
        const data = await response.json();
        if (data.success) {
            closeUserModal();
            fetchData('users');
        } else {
            alert(data.error || 'Failed to save user');
        }
    } catch (err) {
        console.error('Save failed:', err);
    }
}

function getTypeStyle(type) {
    switch (type?.toLowerCase()) {
        case 'pcos': return 'bg-coral-400/10 text-coral-400';
        case 'acne': return 'bg-sage-500/10 text-sage-500';
        case 'weight': return 'bg-blue-400/10 text-blue-400';
        default: return 'bg-sage-100 text-sage-400';
    }
}

function getStatusStyle(status) {
    switch (status?.toLowerCase()) {
        case 'completed': return 'bg-green-400/10 text-green-500';
        case 'follow_up': return 'bg-orange-400/10 text-orange-400';
        case 'pending': return 'bg-gray-100 text-gray-400';
        default: return 'bg-green-400/10 text-green-500';
    }
}

function showDetailModal(assessment) {
    const title = document.getElementById('modal-title');
    const subtitle = document.getElementById('modal-subtitle');
    const body = document.getElementById('modal-body');
    const footer = document.getElementById('modal-footer');

    title.textContent = assessment.name || 'Assessment details';
    subtitle.textContent = `${assessment.assessment_type || 'General'} Result • ${new Date(assessment.created_at).toLocaleDateString()}`;

    // Parse assessment_data if string
    const data = typeof assessment.assessment_data === 'string' ? JSON.parse(assessment.assessment_data) : assessment.assessment_data;
    const notes = typeof assessment.notes === 'string' ? JSON.parse(assessment.notes) : (assessment.notes || []);

    body.innerHTML = `
        <div class="grid grid-cols-2 gap-8">
            <div class="space-y-6">
                <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Contact Information</h3>
                <div class="space-y-4">
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Email Address</div>
                        <div class="text-sage-700">${assessment.email || 'N/A'}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Phone Number</div>
                        <div class="text-sage-700">${assessment.phone || 'N/A'}</div>
                    </div>
                </div>
            </div>
            <div class="space-y-6">
                <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Assessment Status</h3>
                <div class="space-y-4">
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Current Status</div>
                        <select id="update-status" onchange="updateAssessmentStatus('${assessment.id}', this.value)" class="mt-1 w-full px-4 py-2 rounded-xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                            <option value="completed" ${assessment.status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="follow_up" ${assessment.status === 'follow_up' ? 'selected' : ''}>Follow Up</option>
                            <option value="pending" ${assessment.status === 'pending' ? 'selected' : ''}>Pending</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6 pt-8 border-t border-sage-50">
            <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Result Data</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                ${Object.entries(data || {}).map(([key, val]) => `
                    <div class="p-4 rounded-2xl bg-sage-50/50">
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">${key.replace(/_/g, ' ')}</div>
                        <div class="text-sage-700 font-medium">${val}</div>
                    </div>
                `).join('')}
            </div>
        </div>

        <div class="space-y-6 pt-8 border-t border-sage-50">
            <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Admin Notes</h3>
            <div id="notes-list" class="space-y-3">
                ${notes.map(note => `
                    <div class="p-4 rounded-2xl bg-cream-50 border border-cream-100 italic text-sm text-sage-600">
                        ${note}
                    </div>
                `).join('') || '<p class="text-sage-300 text-sm italic">No notes added yet.</p>'}
            </div>
            <div class="flex gap-2">
                <input type="text" id="new-note" placeholder="Add a private note..." class="flex-1 px-4 py-2 rounded-xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                <button onclick="addAssessmentNote('${assessment.id}')" class="px-4 py-2 bg-sage-500 text-white rounded-xl hover:bg-sage-600 transition-colors">Add</button>
            </div>
        </div>
    `;

    openModal();
    lucide.createIcons();
}

async function updateAssessmentStatus(id, status) {
    try {
        const response = await fetch('api/admin_data.php?action=update_assessment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&status=${status}`
        });
        const data = await response.json();
        if (data.success) {
            fetchData('assessments');
        }
    } catch (err) {
        console.error('Update failed:', err);
    }
}

async function addAssessmentNote(id) {
    const input = document.getElementById('new-note');
    const note = input.value.trim();
    if (!note) return;

    try {
        const response = await fetch('api/admin_data.php?action=update_assessment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&note=${encodeURIComponent(note)}`
        });
        const data = await response.json();
        if (data.success) {
            input.value = '';
            // Refresh details
            viewDetails(id);
        }
    } catch (err) {
        console.error('Add note failed:', err);
    }
}

function renderSales(data) {
    const listContainer = document.getElementById('sales-list');
    const revenueEl = document.getElementById('sales-total-revenue');
    if (!listContainer) return;

    // Update Total Revenue
    const totalRev = data.data.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
    if (revenueEl) revenueEl.textContent = `$${totalRev.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

    if (data.data.length === 0) {
        listContainer.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-sage-400">No sales transactions found.</td></tr>`;
        return;
    }

    listContainer.innerHTML = data.data.map(item => `
        <tr class="hover:bg-sage-50/50 transition-colors group">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-sage-900">${item.product_name || 'N/A'}</div>
                <div class="text-[10px] text-sage-300 font-mono mt-0.5 uppercase tracking-tighter">ID: ${item.transaction_id || 'LOCAL'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-sage-700">${item.customer_name || 'Anonymous'}</div>
                <div class="text-xs text-sage-400">${item.customer_email || 'No Email'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-bold text-sage-900">$${parseFloat(item.amount || 0).toLocaleString()}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${getSalesStatusStyle(item.payment_status)}">
                    ${item.payment_status || 'Pending'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <button onclick="viewSaleDetails('${item.id}')" class="p-2 text-sage-400 hover:text-sage-600 transition-colors">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
            </td>
        </tr>
    `).join('');

    lucide.createIcons();
}

function getSalesStatusStyle(status) {
    switch (status?.toLowerCase()) {
        case 'completed': return 'bg-green-400/10 text-green-500';
        case 'failed': return 'bg-red-400/10 text-red-500';
        case 'pending': return 'bg-orange-400/10 text-orange-400';
        case 'refunded': return 'bg-purple-400/10 text-purple-400';
        default: return 'bg-gray-100 text-sage-400';
    }
}

async function viewSaleDetails(id) {
    try {
        const response = await fetch(`api/admin_data.php?action=sale_details&id=${id}`);
        const data = await response.json();
        if (data.success) {
            showSaleDetailModal(data.sale);
        }
    } catch (err) {
        console.error('Error fetching sale details:', err);
    }
}

function showSaleDetailModal(sale) {
    const title = document.getElementById('modal-title');
    const subtitle = document.getElementById('modal-subtitle');
    const body = document.getElementById('modal-body');
    const footer = document.getElementById('modal-footer');

    title.textContent = sale.product_name || 'Transaction Details';
    subtitle.textContent = `Order #${sale.transaction_id || 'LOCAL'} • ${new Date(sale.created_at).toLocaleDateString()}`;

    const notes = typeof sale.notes === 'string' ? JSON.parse(sale.notes) : (sale.notes || []);

    body.innerHTML = `
        <div class="grid grid-cols-2 gap-8">
            <div class="space-y-6">
                <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Customer Details</h3>
                <div class="space-y-4">
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Name</div>
                        <div class="text-sage-700">${sale.customer_name || 'Anonymous'}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Email Address</div>
                        <div class="text-sage-700">${sale.customer_email || 'N/A'}</div>
                    </div>
                </div>
            </div>
            <div class="space-y-6">
                <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Payment Status</h3>
                <div class="space-y-4">
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Current Status</div>
                        <select id="update-sale-status" onchange="updateSaleStatus('${sale.id}', this.value)" class="mt-1 w-full px-4 py-2 rounded-xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                            <option value="completed" ${sale.payment_status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="pending" ${sale.payment_status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="failed" ${sale.payment_status === 'failed' ? 'selected' : ''}>Failed</option>
                            <option value="refunded" ${sale.payment_status === 'refunded' ? 'selected' : ''}>Refunded</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[10px] text-sage-400 uppercase tracking-widest font-bold">Amount</div>
                        <div class="text-lg font-bold text-sage-900">$${parseFloat(sale.amount || 0).toLocaleString()}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6 pt-8 border-t border-sage-50">
            <h3 class="text-sm font-bold uppercase tracking-widest text-sage-300">Transaction Notes</h3>
            <div id="sale-notes-list" class="space-y-3">
                ${notes.map(note => `
                    <div class="p-4 rounded-2xl bg-cream-50 border border-cream-100 italic text-sm text-sage-600">
                        ${note}
                    </div>
                `).join('') || '<p class="text-sage-300 text-sm italic">No transaction notes added.</p>'}
            </div>
            <div class="flex gap-2">
                <input type="text" id="new-sale-note" placeholder="Add a transaction note..." class="flex-1 px-4 py-2 rounded-xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                <button onclick="addSaleNote('${sale.id}')" class="px-4 py-2 bg-sage-500 text-white rounded-xl hover:bg-sage-600 transition-colors">Add</button>
            </div>
        </div>
    `;

    openModal();
    lucide.createIcons();
}

async function updateSaleStatus(id, status) {
    try {
        const response = await fetch('api/admin_data.php?action=update_sale', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&status=${status}`
        });
        const data = await response.json();
        if (data.success) {
            fetchData('sales');
        }
    } catch (err) {
        console.error('Update failed:', err);
    }
}

async function addSaleNote(id) {
    const input = document.getElementById('new-sale-note');
    const note = input.value.trim();
    if (!note) return;

    try {
        const response = await fetch('api/admin_data.php?action=update_sale', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&note=${encodeURIComponent(note)}`
        });
        const data = await response.json();
        if (data.success) {
            input.value = '';
            // Refresh details
            viewSaleDetails(id);
        }
    } catch (err) {
        console.error('Add note failed:', err);
    }
}

function renderTracking(data) {
    const container = document.getElementById('tracking-list');
    if (!container) return;

    if (data.length === 0) {
        container.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-sage-400">No tracking data available.</td></tr>`;
        return;
    }

    container.innerHTML = data.map(item => `
        <tr class="hover:bg-sage-50/50 transition-colors group">
            <td class="px-6 py-4 whitespace-nowrap text-xs text-sage-400">
                ${new Date(item.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sage-500/10 text-sage-500">
                    ${item.funnel_name || 'Global'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-sage-600 font-medium">
                ${item.step_name || 'N/A'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${getEventStyle(item.event_type)}">
                    ${item.event_type || 'View'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-xs text-sage-400">
                ${item.user_email || item.user_id || 'Guest'}
            </td>
        </tr>
    `).join('');

    lucide.createIcons();
}

function getEventStyle(event) {
    switch (event?.toLowerCase()) {
        case 'conversion': return 'bg-green-400/10 text-green-500';
        case 'purchaseintent': return 'bg-coral-400/10 text-coral-400';
        case 'salesvisit': return 'bg-blue-400/10 text-blue-400';
        default: return 'bg-sage-100 text-sage-400';
    }
}

let charts = {};

function renderCharts() {
    if (!adminData || !adminData.chart_labels) return;

    // Destroy existing charts if they exist
    if (charts.conversion) charts.conversion.destroy();

    const ctx = document.getElementById('conversionChart').getContext('2d');
    charts.conversion = new Chart(ctx, {
        type: 'line',
        data: {
            labels: adminData.chart_labels,
            datasets: [
                {
                    label: 'PCOS',
                    data: adminData.daily_conversion.pcos,
                    borderColor: '#D97757', // Coral
                    backgroundColor: 'rgba(217, 119, 87, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Acne',
                    data: adminData.daily_conversion.acne,
                    borderColor: '#2C3E35', // Sage
                    backgroundColor: 'rgba(44, 62, 53, 0.1)',
                    tension: 0.4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, font: { family: 'Inter' } } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => v + '%' } }
            }
        }
    });
}

function handleLogout() {
    window.location.href = 'logout.php';
}

// Initial Run
initialize();
