const adminSummary = document.getElementById("adminSummary");
const adminAlerts = document.getElementById("adminAlerts");
const refreshAdminButton = document.getElementById("refreshAdminButton");
const adminRequestsTableBody = document.getElementById("adminRequestsTableBody");
const adminRequestsEmptyState = document.getElementById("adminRequestsEmptyState");
const collectorNotice = document.getElementById("collectorNotice");
const refreshUsersButton = document.getElementById("refreshUsersButton");
const userManagementAlerts = document.getElementById("userManagementAlerts");
const userSearchInput = document.getElementById("userSearchInput");
const adminUsersTableBody = document.getElementById("adminUsersTableBody");
const adminUsersEmptyState = document.getElementById("adminUsersEmptyState");

let collectors = [];
let allUsers = [];
let currentAdmin = null;

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#39;"
    }[char]));
}

function renderAdmin(user) {
    adminSummary.innerHTML = `
        <div class="meta-item">
            <strong>Admin Name</strong>
            <span>${escapeHtml(user.full_name)}</span>
        </div>
        <div class="meta-item">
            <strong>Email</strong>
            <span>${escapeHtml(user.email)}</span>
        </div>
        <div class="meta-item">
            <strong>Phone</strong>
            <span>${escapeHtml(user.phone || "-")}</span>
        </div>
        <div class="meta-item">
            <strong>Role</strong>
            <span>${escapeHtml(user.role)}</span>
        </div>
    `;
}

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

function statusBadge(status) {
    const variants = {
        pending: "secondary",
        approved: "info",
        assigned: "primary",
        collected: "success",
        cancelled: "danger"
    };
    const variant = variants[status] || "dark";
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : "Unknown";

    return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
}

function roleBadge(role) {
    const variants = {
        user: "secondary",
        collector: "warning",
        admin: "primary"
    };
    const variant = variants[role] || "dark";
    const label = role ? role.charAt(0).toUpperCase() + role.slice(1) : "Unknown";

    return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
}

function itemsSummary(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return "-";
    }

    return items.map((item) => `${item.name}(${item.qty})`).join(", ");
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
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-info approve-button"
                        data-request-id="${request.id}"
                        ${request.status === "pending" ? "" : "disabled"}
                    >
                        Approve
                    </button>
                    <div class="input-group input-group-sm">
                        <select class="form-select collector-select" data-request-id="${request.id}" ${collectors.length === 0 ? "disabled" : ""}>
                            ${collectorOptions(request.collector_id)}
                        </select>
                        <button
                            type="button"
                            class="btn btn-outline-primary assign-button"
                            data-request-id="${request.id}"
                            ${collectors.length === 0 ? "disabled" : (request.collector_id ? "" : "disabled")}
                        >
                            Assign
                        </button>
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

    return allUsers.filter((user) => {
        const haystack = `${user.full_name} ${user.email}`.toLowerCase();
        return haystack.includes(term);
    });
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
                <td>${roleBadge(user.role)}</td>
                <td>${escapeHtml(user.created_at)}</td>
                <td>
                    <div class="input-group input-group-sm">
                        <select class="form-select role-select" data-user-id="${user.id}">
                            <option value="user" ${user.role === "user" ? "selected" : ""}>user</option>
                            <option value="collector" ${user.role === "collector" ? "selected" : ""}>collector</option>
                            <option value="admin" ${user.role === "admin" ? "selected" : ""}>admin</option>
                        </select>
                        <button
                            type="button"
                            class="btn btn-outline-primary update-role-button"
                            data-user-id="${user.id}"
                            ${isSelf ? 'title="You cannot remove your own admin role."' : ""}
                        >
                            Update
                        </button>
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

    collectorNotice.textContent = collectors.length === 0
        ? "No collectors available"
        : `Collectors available: ${collectors.length}`;
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
    } catch (error) {
        button.disabled = false;
        button.textContent = "Approve";
        showAlert(error.message || "Failed to approve request", "danger");
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
    } catch (error) {
        button.disabled = false;
        select.disabled = false;
        button.textContent = "Assign";
        showAlert(error.message || "Failed to assign collector", "danger");
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
    await renderNavbar();
    const user = await requireRole("admin");

    if (!user) {
        return;
    }

    currentAdmin = user;
    renderAdmin(user);
    await refreshAdminData();
}

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
userSearchInput.addEventListener("input", () => {
    renderUsers(filteredUsers());
});

loadPage().catch((error) => {
    showAlert(error.message || "Failed to load admin page", "danger");
});
