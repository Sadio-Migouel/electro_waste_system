let collectorSummary;
let collectorAlerts;
let refreshCollectorButton;
let collectorRequestsTableBody;
let collectorRequestsEmptyState;

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

function actionCell(request) {
    if (request.status === "collected") {
        return '<button type="button" class="btn btn-sm btn-success" disabled>Done</button>';
    }

    if (request.status === "assigned") {
        return `
            <button type="button" class="btn btn-sm btn-outline-success mark-collected-button" data-request-id="${request.id}">
                Mark Collected
            </button>
        `;
    }

    return '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Unavailable</button>';
}

function renderCollector(user) {
    collectorSummary.innerHTML = `
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Collector Name</strong><span>${escapeHtml(user.full_name)}</span></div>
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Email</strong><span>${escapeHtml(user.email)}</span></div>
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Phone</strong><span>${escapeHtml(user.phone || "-")}</span></div>
            <div class="col-sm-6 col-lg-3"><strong class="d-block small text-secondary mb-1">Role</strong><span>${escapeHtml(user.role)}</span></div>
        </div>
    `;
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
    } catch (err) {
        console.error("Failed to mark request as collected", err);
        button.disabled = false;
        button.textContent = "Mark Collected";
        showAlert(`Failed to mark request as collected: ${err.message}`, "danger");
    }
}

async function loadPage() {
    const user = await initLayout("collector", "Collector Dashboard");

    if (!user) {
        return;
    }

    const pageOutlet = document.getElementById("pageOutlet");
    pageOutlet.innerHTML = `
        <main class="d-grid gap-4">
            <section class="surface-card page-heading mb-0">
                <span class="page-chip"><i class="bi bi-truck"></i> Collector</span>
                <h2 class="mt-3">Collector Dashboard</h2>
                <p class="text-secondary mb-4">Review requests assigned to you and mark completed pickups as collected.</p>
                <div id="collectorSummary"></div>
            </section>

            <section class="surface-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">My Assigned Requests</h3>
                        <p class="text-secondary mb-0">Keep tabs on assigned collections from any screen size.</p>
                    </div>
                    <button id="refreshCollectorButton" type="button" class="btn btn-outline-secondary btn-sm">Refresh</button>
                </div>

                <div id="collectorAlerts" class="mb-3" aria-live="polite"></div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Address</th>
                                <th>Items</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="collectorRequestsTableBody"></tbody>
                    </table>
                </div>

                <div id="collectorRequestsEmptyState" class="text-secondary small d-none mt-3">No assigned requests found.</div>
            </section>
        </main>
    `;

    collectorSummary = document.getElementById("collectorSummary");
    collectorAlerts = document.getElementById("collectorAlerts");
    refreshCollectorButton = document.getElementById("refreshCollectorButton");
    collectorRequestsTableBody = document.getElementById("collectorRequestsTableBody");
    collectorRequestsEmptyState = document.getElementById("collectorRequestsEmptyState");

    renderCollector(user);
    refreshCollectorButton.addEventListener("click", refreshCollectorData);
    await refreshCollectorData();
}

loadPage().catch((error) => {
    const pageOutlet = document.getElementById("pageOutlet");
    if (pageOutlet) {
        pageOutlet.innerHTML = `<div class="alert alert-danger" role="alert">${escapeHtml(error.message || "Failed to load collector page")}</div>`;
    }
});

