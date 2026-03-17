const profileSummary = document.getElementById("profileSummary");
const feedbackHost = document.getElementById("requestFeedback");
const requestForm = document.getElementById("requestForm");
const addressInput = document.getElementById("address");
const itemsContainer = document.getElementById("itemsContainer");
const addItemButton = document.getElementById("addItemButton");
const requestsTableBody = document.getElementById("requestsTableBody");
const requestsEmptyState = document.getElementById("requestsEmptyState");
const refreshRequestsButton = document.getElementById("refreshRequestsButton");
const notificationsList = document.getElementById("notificationsList");
const notificationsEmptyState = document.getElementById("notificationsEmptyState");
const notificationsBadge = document.getElementById("notificationsBadge");
const refreshNotificationsButton = document.getElementById("refreshNotificationsButton");
const markAllReadButton = document.getElementById("markAllReadButton");
const timelineModalElement = document.getElementById("timelineModal");
const timelineFeedback = document.getElementById("timelineFeedback");
const timelineList = document.getElementById("timelineList");
const timelineEmptyState = document.getElementById("timelineEmptyState");
const timelineModal = timelineModalElement ? new bootstrap.Modal(timelineModalElement) : null;

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

function setTimelineFeedback(message = "", type = "danger") {
    timelineFeedback.innerHTML = message ? `
        <div class="alert alert-${type} mb-0" role="alert">
            ${escapeHtml(message)}
        </div>
    ` : "";
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
            <td>
                <button type="button" class="btn btn-outline-primary btn-sm timeline-button" data-request-id="${request.id}">
                    View timeline
                </button>
            </td>
        </tr>
    `).join("");
}

function renderNotifications(notifications) {
    const recentNotifications = Array.isArray(notifications) ? notifications.slice(0, 5) : [];
    const unreadCount = Array.isArray(notifications)
        ? notifications.filter((notification) => Number(notification.is_read) === 0).length
        : 0;

    notificationsBadge.textContent = `${unreadCount} unread`;
    notificationsBadge.classList.toggle("d-none", unreadCount === 0);

    if (recentNotifications.length === 0) {
        notificationsList.innerHTML = "";
        notificationsEmptyState.classList.remove("d-none");
        markAllReadButton.disabled = true;
        return;
    }

    notificationsEmptyState.classList.add("d-none");
    markAllReadButton.disabled = unreadCount === 0;
    notificationsList.innerHTML = recentNotifications.map((notification) => `
        <article class="notification-item ${Number(notification.is_read) === 0 ? "is-unread" : ""}">
            <div class="d-flex justify-content-between gap-3">
                <div>
                    <div class="notification-title">
                        ${escapeHtml(notification.title)}
                        ${Number(notification.is_read) === 0 ? '<span class="badge text-bg-success ms-2">New</span>' : ""}
                    </div>
                    <div>${escapeHtml(notification.message)}</div>
                </div>
                <div class="notification-meta text-nowrap">${escapeHtml(notification.created_at)}</div>
            </div>
        </article>
    `).join("");
}

function renderTimeline(history, requestId) {
    setTimelineFeedback("");
    timelineEmptyState.classList.toggle("d-none", Array.isArray(history) && history.length > 0);

    if (!Array.isArray(history) || history.length === 0) {
        timelineList.innerHTML = "";
        return;
    }

    timelineList.innerHTML = history.map((entry) => `
        <article class="timeline-item">
            <div class="d-flex justify-content-between gap-3">
                <div>
                    <div class="timeline-status">${statusBadge(entry.status)}</div>
                    <div class="mt-2">${escapeHtml(entry.note || `Status changed to ${entry.status}`)}</div>
                </div>
                <div class="timeline-meta text-nowrap">${escapeHtml(entry.created_at)}</div>
            </div>
        </article>
    `).join("");

    const modalLabel = document.getElementById("timelineModalLabel");

    if (modalLabel) {
        modalLabel.textContent = `Request #${requestId} Timeline`;
    }
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

async function loadNotifications() {
    refreshNotificationsButton.disabled = true;
    refreshNotificationsButton.textContent = "Refreshing...";

    try {
        const result = await api("notifications_list.php");
        renderNotifications(result.notifications || []);

        if (typeof loadNavbarNotifications === "function") {
            await loadNavbarNotifications();
        }
    } catch (error) {
        notificationsList.innerHTML = "";
        notificationsEmptyState.classList.remove("d-none");
        showFeedback(error.message || "Failed to load notifications", "danger");
    } finally {
        refreshNotificationsButton.disabled = false;
        refreshNotificationsButton.textContent = "Refresh";
    }
}

async function markAllRead() {
    markAllReadButton.disabled = true;
    markAllReadButton.textContent = "Marking...";

    try {
        await api("notifications_mark_read.php", "POST", {
            mark_all: true
        });
        await loadNotifications();
    } catch (error) {
        showFeedback(error.message || "Failed to mark notifications as read", "danger");
    } finally {
        markAllReadButton.textContent = "Mark all read";
    }
}

async function openTimeline(requestId) {
    if (!timelineModal) {
        return;
    }

    setTimelineFeedback("");
    timelineList.innerHTML = '<div class="text-secondary small">Loading timeline...</div>';
    timelineEmptyState.classList.add("d-none");
    timelineModal.show();

    try {
        const result = await api("request_timeline.php", "POST", {
            request_id: requestId
        });
        renderTimeline(result.history || [], requestId);
    } catch (error) {
        timelineList.innerHTML = "";
        timelineEmptyState.classList.add("d-none");
        setTimelineFeedback(error.message || "Failed to load timeline", "danger");
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
        await loadNotifications();
    } catch (err) {
        console.error("Failed to create request", err);
        showFeedback(`Failed to create request: ${err.message}`, "danger");
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
    await loadNotifications();
}

addItemButton.addEventListener("click", () => addItemRow());
refreshRequestsButton.addEventListener("click", loadRequests);
refreshNotificationsButton.addEventListener("click", loadNotifications);
markAllReadButton.addEventListener("click", markAllRead);
requestForm.addEventListener("submit", submitRequest);
requestsTableBody.addEventListener("click", (event) => {
    const button = event.target.closest(".timeline-button");

    if (!button) {
        return;
    }

    const requestId = Number.parseInt(button.dataset.requestId || "", 10);

    if (!Number.isInteger(requestId) || requestId <= 0) {
        return;
    }

    openTimeline(requestId);
});

loadPage().catch((error) => {
    showFeedback(error.message || "Failed to load page", "danger");
});

