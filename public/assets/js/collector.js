const collectorSummary = document.getElementById("collectorSummary");
const collectorAlerts = document.getElementById("collectorAlerts");
const refreshCollectorButton = document.getElementById("refreshCollectorButton");
const collectorRequestsTableBody = document.getElementById("collectorRequestsTableBody");
const collectorRequestsEmptyState = document.getElementById("collectorRequestsEmptyState");

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#39;"
    }[char]));
}

function renderCollector(user) {
    collectorSummary.innerHTML = `
        <div class="meta-item">
            <strong>Collector Name</strong>
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
    collectorAlerts.innerHTML = `
        <div class="alert alert-${type} mb-0" role="alert">
            ${escapeHtml(message)}
        </div>
    `;
}

function clearAlert() {
    collectorAlerts.innerHTML = "";
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

function itemsSummary(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return "-";
    }

    return items.map((item) => `${item.name}(${item.qty})`).join(", ");
}

function actionCell(request) {
    if (request.status === "collected") {
        return '<button type="button" class="btn btn-sm btn-success" disabled>Done</button>';
    }

    if (request.status === "assigned") {
        return `
            <button
                type="button"
                class="btn btn-sm btn-outline-success mark-collected-button"
                data-request-id="${request.id}"
            >
                Mark Collected
            </button>
        `;
    }

    return '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Unavailable</button>';
}

function renderRequests(requests) {
    if (!Array.isArray(requests) || requests.length === 0) {
        collectorRequestsTableBody.innerHTML = "";
        collectorRequestsEmptyState.classList.remove("d-none");
        return;
    }

    collectorRequestsEmptyState.classList.add("d-none");
    collectorRequestsTableBody.innerHTML = requests.map((request) => `
        <tr>
            <td>${request.id}</td>
            <td>${statusBadge(request.status)}</td>
            <td>${escapeHtml(request.address)}</td>
            <td>${escapeHtml(itemsSummary(request.items))}</td>
            <td>${escapeHtml(request.created_at)}</td>
            <td>${actionCell(request)}</td>
        </tr>
    `).join("");

    bindTableActions();
}

function bindTableActions() {
    document.querySelectorAll(".mark-collected-button").forEach((button) => {
        button.addEventListener("click", async (event) => {
            const requestId = Number.parseInt(event.currentTarget.dataset.requestId, 10);
            await markCollected(requestId, event.currentTarget);
        });
    });
}

async function loadRequests() {
    const result = await api("collector_assigned_requests.php");
    renderRequests(result.requests || []);
}

async function refreshCollectorData() {
    clearAlert();
    refreshCollectorButton.disabled = true;
    refreshCollectorButton.textContent = "Refreshing...";

    try {
        await loadRequests();
    } catch (error) {
        collectorRequestsTableBody.innerHTML = "";
        collectorRequestsEmptyState.classList.remove("d-none");
        showAlert(error.message || "Failed to load assigned requests", "danger");
    } finally {
        refreshCollectorButton.disabled = false;
        refreshCollectorButton.textContent = "Refresh";
    }
}

async function markCollected(requestId, button) {
    clearAlert();
    button.disabled = true;
    button.textContent = "Saving...";

    try {
        await api("collector_mark_collected.php", "POST", {
            request_id: requestId
        });
        showAlert("Request marked as collected.");
        await refreshCollectorData();
    } catch (error) {
        button.disabled = false;
        button.textContent = "Mark Collected";
        showAlert(error.message || "Failed to mark request as collected", "danger");
    }
}

async function loadPage() {
    await renderNavbar();
    const user = await requireRole("collector");

    if (!user) {
        return;
    }

    renderCollector(user);
    await refreshCollectorData();
}

refreshCollectorButton.addEventListener("click", refreshCollectorData);

loadPage().catch((error) => {
    showAlert(error.message || "Failed to load collector page", "danger");
});
