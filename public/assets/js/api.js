const API_BASE = "../Api";

async function api(path, method = "GET", body) {
    const options = {
        method,
        headers: {
            "Content-Type": "application/json"
        },
        credentials: "include"
    };

    if (body !== undefined) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(`${API_BASE}/${path}`, options);
    const rawText = await response.text();

    let data = {};

    if (rawText) {
        try {
            data = JSON.parse(rawText);
        } catch (err) {
            console.error("Invalid JSON response", {
                path,
                status: response.status,
                rawText
            });
            throw new Error(rawText ? `Invalid JSON response: ${rawText}` : "Invalid JSON response");
        }
    }

    if (!response.ok) {
        const requestError = new Error(
            typeof data.error === "string" && data.error.trim() !== ""
                ? data.error
                : `Request failed with status ${response.status}`
        );
        requestError.status = response.status;
        requestError.data = data;
        throw requestError;
    }

    return data;
}
