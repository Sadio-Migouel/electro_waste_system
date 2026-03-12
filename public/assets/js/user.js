const profileSummary = document.getElementById("profileSummary");
const feedbackHost = document.getElementById("requestFeedback");
const requestForm = document.getElementById("requestForm");
const addressInput = document.getElementById("address");
const itemsContainer = document.getElementById("itemsContainer");
const addItemButton = document.getElementById("addItemButton");
const requestsTableBody = document.getElementById("requestsTableBody");
const requestsEmptyState = document.getElementById("requestsEmptyState");
const refreshRequestsButton = document.getElementById("refreshRequestsButton");

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#39;"
    }[char]));
}

function renderProfile(user) {
    profileSummary.innerHTML = `
        <div class="meta-item">
            <strong>Full Name</strong>
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

function showFeedback(message, type = "success") {
    feedbackHost.innerHTML = `
        <div class="alert alert-${type} mb-0" role="alert">
            ${escapeHtml(message)}
        </div>
    `;
}

function clearFeedback() {
    feedbackHost.innerHTML = "";
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

    return items
        .map((item) => `${item.name}(${item.qty})`)
        .join(", ");
}

function createItemRow(item = { name: "", qty: 1 }) {
    const row = document.createElement("div");
    row.className = "row g-2 align-items-end item-row";
    row.innerHTML = `
        <div class="col-sm-7">
            <label class="form-label">Item name</label>
            <input type="text" class="form-control item-name" placeholder="Laptop, phone, battery..." value="${escapeHtml(item.name)}">
        </div>
        <div class="col-sm-3">
            <label class="form-label">Qty</label>
            <input type="number" class="form-control item-qty" min="1" step="1" value="${escapeHtml(item.qty)}">
        </div>
        <div class="col-sm-2 d-grid">
            <button type="button" class="btn btn-outline-danger remove-item-button">Remove</button>
        </div>
    `;

    const removeButton = row.querySelector(".remove-item-button");
    removeButton.addEventListener("click", () => {
        row.remove();

        if (itemsContainer.children.length === 0) {
            addItemRow();
        }
    });

    return row;
}

function addItemRow(item) {
    itemsContainer.appendChild(createItemRow(item));
}

function collectItems() {
    return Array.from(itemsContainer.querySelectorAll(".item-row")).map((row) => ({
        name: row.querySelector(".item-name").value.trim(),
        qty: Number.parseInt(row.querySelector(".item-qty").value, 10)
    }));
}

function renderRequests(requests) {
    if (!Array.isArray(requests) || requests.length === 0) {
        requestsTableBody.innerHTML = "";
        requestsEmptyState.classList.remove("d-none");
        return;
    }

    requestsEmptyState.classList.add("d-none");
    requestsTableBody.innerHTML = requests.map((request) => `
        <tr>
            <td>${request.id}</td>
            <td>${statusBadge(request.status)}</td>
            <td>${escapeHtml(request.address)}</td>
            <td>${escapeHtml(itemsSummary(request.items))}</td>
            <td>${escapeHtml(request.created_at)}</td>
        </tr>
    `).join("");
}

async function loadRequests() {
    refreshRequestsButton.disabled = true;
    refreshRequestsButton.textContent = "Refreshing...";

    try {
        const result = await api("requests_list.php");
        renderRequests(result.requests || []);
    } catch (error) {
        requestsTableBody.innerHTML = "";
        requestsEmptyState.classList.remove("d-none");
        showFeedback(error.message || "Failed to load requests", "danger");
    } finally {
        refreshRequestsButton.disabled = false;
        refreshRequestsButton.textContent = "Refresh";
    }
}

async function submitRequest(event) {
    event.preventDefault();
    clearFeedback();

    const submitButton = requestForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.textContent = "Submitting...";

    try {
        await api("requests_create.php", "POST", {
            address: addressInput.value.trim(),
            items: collectItems()
        });

        showFeedback("Request created successfully.", "success");
        requestForm.reset();
        itemsContainer.innerHTML = "";
        addItemRow();
        await loadRequests();
    } catch (error) {
        showFeedback(error.message || "Failed to create request", "danger");
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = "Submit Request";
    }
}

async function loadPage() {
    await renderNavbar();
    const user = await requireRole("user");

    if (!user) {
        return;
    }

    renderProfile(user);
    addItemRow();
    await loadRequests();
}

addItemButton.addEventListener("click", () => addItemRow());
refreshRequestsButton.addEventListener("click", loadRequests);
requestForm.addEventListener("submit", submitRequest);

loadPage().catch((error) => {
    showFeedback(error.message || "Failed to load page", "danger");
});
