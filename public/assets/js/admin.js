let adminSummary;
let adminAlerts;
let refreshAdminButton;
let adminRequestsTableBody;
let adminRequestsEmptyState;
let collectorNotice;
let refreshUsersButton;
let userManagementAlerts;
let userSearchInput;
let adminUsersTableBody;
let adminUsersEmptyState;

let collectors = [];
let allUsers = [];
let currentAdmin = null;

function showAlert(message, type = "success") {
    adminAlerts.innerHTML = `
        <div class="alert alert-${type} mb-0" role="alert">
            ${escapeHtml(message)}
        </div>
    `;
}

function clearAlert() {
    adminAlerts.innerHTML = "";
}

function showUserAlert(message, type = "success") {
    userManagementAlerts.innerHTML = `
        <div class="alert alert-${type} mb-0" role="alert">
            ${escapeHtml(message)}
        </div>
    `;
}

function clearUserAlert() {
    userManagementAlerts.innerHTML = "";
}

function roleBadgeLocal(role) {
    const variants = {
        user: "secondary",
        collector: "warning",
        admin: "primary"
    };
    const variant = variants[role] || "dark";
    const label = role ? role.charAt(0).toUpperCase() + role.slice(1) : "Unknown";

    return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
}

function collectorSummary(request) {
    if (!request.collector_name) {
        return '<span class="text-secondary">Unassigned</span>';
    }

    const details = [request.collector_name];

    if (request.collector_email) {
        details.push(request.collector_email);
    }

    return details.map((detail) => `<div>${escapeHtml(detail)}</div>`).join("");
}

function collectorOptions(selectedCollectorId) {
    if (collectors.length === 0) {
        return '<option value="">No collectors available</option>';
    }

    const placeholder = `<option value="">${selectedCollectorId ? "Reassign collector" : "Select collector"}</option>`;
    const options = collectors.map((collector) => `
        <option value="${collector.id}" ${collector.id === selectedCollectorId ? "selected" : ""}>
            ${escapeHtml(collector.full_name)}${collector.email ? ` (${escapeHtml(collector.email)})` : ""}
        </option>
    `).join("");

    return placeholder + options;
}

function renderAdmin(user) {
    adminSummary.innerHTML = `
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Admin Name</strong><span>${escapeHtml(user.full_name)}</span></div>
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Email</strong><span>${escapeHtml(user.email)}</span></div>
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Phone</strong><span>${escapeHtml(user.phone || "-")}</span></div>
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Role</strong><span>${escapeHtml(user.role)}</span></div>
        </div>
    `;
}

function renderRequests(requests) {
    if (!Array.isArray(requests) || requests.length === 0) {
        adminRequestsTableBody.innerHTML = "";
        adminRequestsEmptyState.classList.remove("d-none");
        return;
    }

    adminRequestsEmptyState.classList.add("d-none");
    adminRequestsTableBody.innerHTML = requests.map((request) => `
        <tr>
            <td>${request.id}</td>
            <td>${statusBadge(request.status)}</td>
            <td>${escapeHtml(request.address)}</td>
            <td>${escapeHtml(itemsSummary(request.items))}</td>
            <td>${escapeHtml(request.created_at)}</td>
            <td>${collectorSummary(request)}</td>
            <td>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-sm btn-outline-info approve-button" data-request-id="${request.id}" ${request.status === "pending" ? "" : "disabled"}>Approve</button>
                    <div class="input-group input-group-sm flex-wrap flex-md-nowrap">
                        <select class="form-select collector-select" data-request-id="${request.id}" ${collectors.length === 0 ? "disabled" : ""}>
                            ${collectorOptions(request.collector_id)}
                        </select>
                        <button type="button" class="btn btn-outline-primary assign-button" data-request-id="${request.id}" ${collectors.length === 0 ? "disabled" : (request.collector_id ? "" : "disabled")}>Assign</button>
                    </div>
                </div>
            </td>
        </tr>
    `).join("");

    bindTableActions();
}

function filteredUsers() {
    const term = (userSearchInput.value || "").trim().toLowerCase();

    if (term === "") {
        return allUsers;
    }

    return allUsers.filter((user) => `${user.full_name} ${user.email}`.toLowerCase().includes(term));
}

function renderUsers(users) {
    if (!Array.isArray(users) || users.length === 0) {
        adminUsersTableBody.innerHTML = "";
        adminUsersEmptyState.classList.remove("d-none");
        return;
    }

    adminUsersEmptyState.classList.add("d-none");
    adminUsersTableBody.innerHTML = users.map((user) => {
        const isSelf = currentAdmin && Number(user.id) === Number(currentAdmin.id);

        return `
            <tr>
                <td>${user.id}</td>
                <td>${escapeHtml(user.full_name)}</td>
                <td>${escapeHtml(user.email)}</td>
                <td>${escapeHtml(user.phone || "-")}</td>
                <td>${roleBadgeLocal(user.role)}</td>
                <td>${escapeHtml(user.created_at)}</td>
                <td>
                    <div class="input-group input-group-sm flex-wrap flex-md-nowrap">
                        <select class="form-select role-select" data-user-id="${user.id}">
                            <option value="user" ${user.role === "user" ? "selected" : ""}>user</option>
                            <option value="collector" ${user.role === "collector" ? "selected" : ""}>collector</option>
                            <option value="admin" ${user.role === "admin" ? "selected" : ""}>admin</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary update-role-button" data-user-id="${user.id}" ${isSelf ? 'title="You cannot remove your own admin role."' : ""}>Update</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    bindUserActions();
}

function bindUserActions() {
    document.querySelectorAll(".update-role-button").forEach((button) => {
        button.addEventListener("click", async (event) => {
            const userId = Number.parseInt(event.currentTarget.dataset.userId, 10);
            const select = document.querySelector(`.role-select[data-user-id="${userId}"]`);
            const role = select ? select.value : "";
            await updateUserRole(userId, role, event.currentTarget, select);
        });
    });
}

function bindTableActions() {
    document.querySelectorAll(".collector-select").forEach((select) => {
        select.addEventListener("change", (event) => {
            const requestId = event.target.dataset.requestId;
            const assignButton = document.querySelector(`.assign-button[data-request-id="${requestId}"]`);

            if (assignButton) {
                assignButton.disabled = event.target.value === "";
            }
        });
    });

    document.querySelectorAll(".approve-button").forEach((button) => {
        button.addEventListener("click", async (event) => {
            const requestId = Number.parseInt(event.currentTarget.dataset.requestId, 10);
            await approveRequest(requestId, event.currentTarget);
        });
    });

    document.querySelectorAll(".assign-button").forEach((button) => {
        button.addEventListener("click", async (event) => {
            const requestId = Number.parseInt(event.currentTarget.dataset.requestId, 10);
            const select = document.querySelector(`.collector-select[data-request-id="${requestId}"]`);
            const collectorId = Number.parseInt(select.value, 10);
            await assignCollector(requestId, collectorId, event.currentTarget, select);
        });
    });
}

async function loadCollectors() {
    const result = await api("collectors_list.php");
    collectors = Array.isArray(result.collectors) ? result.collectors : [];
    collectorNotice.textContent = collectors.length === 0 ? "No collectors available" : `Collectors available: ${collectors.length}`;
}

async function loadRequests() {
    const result = await api("requests_list.php");
    renderRequests(result.requests || []);
}

async function loadUsers() {
    const result = await api("admin_users_list.php");
    allUsers = Array.isArray(result.users) ? result.users : [];
    renderUsers(filteredUsers());
}

async function refreshAdminData() {
    clearAlert();
    clearUserAlert();
    refreshAdminButton.disabled = true;
    refreshAdminButton.textContent = "Refreshing...";
    refreshUsersButton.disabled = true;
    refreshUsersButton.textContent = "Refreshing...";

    try {
        await loadCollectors();
        await loadRequests();
        await loadUsers();
    } catch (error) {
        if (!adminRequestsTableBody.innerHTML) {
            adminRequestsTableBody.innerHTML = "";
            adminRequestsEmptyState.classList.remove("d-none");
        }
        if (!adminUsersTableBody.innerHTML) {
            adminUsersTableBody.innerHTML = "";
            adminUsersEmptyState.classList.remove("d-none");
        }
        showAlert(error.message || "Failed to load admin data", "danger");
    } finally {
        refreshAdminButton.disabled = false;
        refreshAdminButton.textContent = "Refresh";
        refreshUsersButton.disabled = false;
        refreshUsersButton.textContent = "Refresh Users";
    }
}

async function approveRequest(requestId, button) {
    clearAlert();
    button.disabled = true;
    button.textContent = "Approving...";

    try {
        await api("requests_update_status.php", "POST", {
            request_id: requestId,
            status: "approved"
        });
        showAlert("Request approved.");
        await refreshAdminData();
    } catch (err) {
        console.error("Failed to approve", err);
        button.disabled = false;
        button.textContent = "Approve";
        showAlert(`Failed to approve: ${err.message}`, "danger");
    }
}

async function assignCollector(requestId, collectorId, button, select) {
    if (!Number.isInteger(collectorId) || collectorId <= 0) {
        showAlert("Please select a collector first.", "danger");
        return;
    }

    clearAlert();
    button.disabled = true;
    select.disabled = true;
    button.textContent = "Assigning...";

    try {
        await api("requests_assign.php", "POST", {
            request_id: requestId,
            collector_id: collectorId
        });
        showAlert("Collector assigned.");
        await refreshAdminData();
    } catch (err) {
        console.error("Failed to assign", err);
        button.disabled = false;
        select.disabled = false;
        button.textContent = "Assign";
        showAlert(`Failed to assign: ${err.message}`, "danger");
    }
}

async function updateUserRole(userId, role, button, select) {
    if (!Number.isInteger(userId) || userId <= 0) {
        showUserAlert("Invalid user selected.", "danger");
        return;
    }

    clearUserAlert();
    button.disabled = true;
    select.disabled = true;
    button.textContent = "Updating...";

    try {
        await api("admin_set_role.php", "POST", {
            user_id: userId,
            role
        });
        showUserAlert("Role updated.");
        await loadCollectors();
        await loadUsers();
        await loadRequests();
    } catch (error) {
        showUserAlert(error.message || "Failed to update role", "danger");
    } finally {
        button.disabled = false;
        select.disabled = false;
        button.textContent = "Update";
    }
}

async function loadPage() {
    const user = await initLayout("admin", "Admin Dashboard");

    if (!user) {
        return;
    }

    const pageOutlet = document.getElementById("pageOutlet");
    pageOutlet.innerHTML = `
        <main class="d-grid gap-4">
            <section id="manage-requests" class="surface-card page-heading mb-0">
                <span class="page-chip"><i class="bi bi-shield-lock"></i> Admin</span>
                <h2 class="mt-3">Admin Dashboard</h2>
                <p class="text-secondary mb-4">Approve requests, assign collectors, and manage accounts from one responsive workspace.</p>
                <div id="adminSummary"></div>
            </section>

            <section id="manage-requests-panel" class="surface-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Approve & Assign Requests</h3>
                        <p class="text-secondary mb-0">Review all pickup requests and assign them to available collectors.</p>
                    </div>
                    <button id="refreshAdminButton" type="button" class="btn btn-outline-secondary btn-sm">Refresh</button>
                </div>

                <div id="adminAlerts" class="mb-3" aria-live="polite"></div>
                <div id="collectorNotice" class="small text-secondary mb-3">Loading collectors...</div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Address</th>
                                <th>Items</th>
                                <th>Created At</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adminRequestsTableBody"></tbody>
                    </table>
                </div>

                <div id="adminRequestsEmptyState" class="text-secondary small d-none mt-3">No pickup requests found.</div>
            </section>

            <section id="manage-users" class="surface-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Manage Users</h3>
                        <p class="text-secondary mb-0">Search accounts and change roles for users, collectors, and admins.</p>
                    </div>
                    <button id="refreshUsersButton" type="button" class="btn btn-outline-secondary btn-sm">Refresh Users</button>
                </div>

                <div id="userManagementAlerts" class="mb-3" aria-live="polite"></div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="userSearchInput" class="form-label">Search users</label>
                        <input id="userSearchInput" type="search" class="form-control soft-input" placeholder="Filter by name or email">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Change Role</th>
                            </tr>
                        </thead>
                        <tbody id="adminUsersTableBody"></tbody>
                    </table>
                </div>

                <div id="adminUsersEmptyState" class="text-secondary small d-none mt-3">No users found.</div>
            </section>
        </main>
    `;

    adminSummary = document.getElementById("adminSummary");
    adminAlerts = document.getElementById("adminAlerts");
    refreshAdminButton = document.getElementById("refreshAdminButton");
    adminRequestsTableBody = document.getElementById("adminRequestsTableBody");
    adminRequestsEmptyState = document.getElementById("adminRequestsEmptyState");
    collectorNotice = document.getElementById("collectorNotice");
    refreshUsersButton = document.getElementById("refreshUsersButton");
    userManagementAlerts = document.getElementById("userManagementAlerts");
    userSearchInput = document.getElementById("userSearchInput");
    adminUsersTableBody = document.getElementById("adminUsersTableBody");
    adminUsersEmptyState = document.getElementById("adminUsersEmptyState");

    currentAdmin = user;
    renderAdmin(user);

    refreshAdminButton.addEventListener("click", refreshAdminData);
    refreshUsersButton.addEventListener("click", async () => {
        clearUserAlert();
        refreshUsersButton.disabled = true;
        refreshUsersButton.textContent = "Refreshing...";

        try {
            await loadUsers();
        } catch (error) {
            adminUsersTableBody.innerHTML = "";
            adminUsersEmptyState.classList.remove("d-none");
            showUserAlert(error.message || "Failed to load users", "danger");
        } finally {
            refreshUsersButton.disabled = false;
            refreshUsersButton.textContent = "Refresh Users";
        }
    });
    userSearchInput.addEventListener("input", () => renderUsers(filteredUsers()));

    await refreshAdminData();
}

loadPage().catch((error) => {
    const pageOutlet = document.getElementById("pageOutlet");
    if (pageOutlet) {
        pageOutlet.innerHTML = `<div class="alert alert-danger" role="alert">${escapeHtml(error.message || "Failed to load admin page")}</div>`;
    }
});

