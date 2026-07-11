window.addEventListener("pageshow", function (event) {
  // Check if the modal is open
  if (document.querySelector("#modalLoading.show")) {
    let modal = bootstrap.Modal.getInstance(
      document.getElementById("modalLoading")
    );
    if (modal) {
      modal.hide();
    } else {
      // Fallback: force hide by removing classes
      document.getElementById("modalLoading").classList.remove("show");
      document.body.classList.remove("modal-open");
      document.querySelectorAll(".modal-backdrop").forEach((e) => e.remove());
    }
  }

  if (event.persisted) {
    window.location.reload();
  }
});

function toCamelCase(str) {
  return str
    .replace(/[_\s]+(.)?/g, (_, c) => (c ? c.toUpperCase() : ""))
    .replace(/^(.)/, (_, c) => c.toLowerCase());
}

function csvToCamelCaseArray(str, delimiter = ",") {
  const rows = str.trim().split("\n");
  const headers = rows
    .shift()
    .split(delimiter)
    .map((h) => toCamelCase(h.trim()));

  return rows.map((row) => {
    const values = row.split(delimiter);
    return headers.reduce((obj, key, index) => {
      obj[key] = values[index]?.trim();
      return obj;
    }, {});
  });
}

/**
 * Validate and parse RRN CSV text.
 * Expected header (exact): Merchant PAN,Merchant Id,RRN,Date
 * Returns { valid, errors[], rows[] }
 */
function parseAndValidateRrnCsv(csvText, opts = {}) {
  const config = {
    allowFutureDate: false,
    maxRows: 1000,
    ...opts,
  };

  // Remove BOM and normalize line endings
  csvText = csvText.replace(/^\uFEFF/, "").replace(/\r\n?/g, "\n");

  const lines = csvText.split("\n").filter((l) => l.trim() !== "");
  const errors = [];
  const rows = [];

  if (lines.length === 0) {
    return { valid: false, errors: ["File is empty"], rows: [] };
  }

  const expectedHeader = ["merchant id"];
  const rawHeader = lines[0].split(",").map((h) => h.trim());

  if (
    rawHeader.length !== expectedHeader.length ||
    !expectedHeader.every((h, i) => h === rawHeader[i])
  ) {
    errors.push(
      `Invalid header. <b>Expected:</b> "${expectedHeader.join(
        ","
      )}" <b>but got:</b> "${rawHeader.join(",")}"`
    );
    return { valid: false, errors, rows: [] };
  }

  for (let i = 1; i < lines.length; i++) {
    if (rows.length >= config.maxRows) {
      errors.push(`Row limit exceeded (max ${config.maxRows}).`);
      break;
    }

    const line = lines[i];
    const cols = line.split(",").map((c) => c.trim());

    if (cols.length !== 1) {
      errors.push(`Row ${i}: Expected 1 columns, found ${cols.length}`);
      continue;
    }

    const [merchantId, rrn, dateStr] = cols;
    let rowHasError = false;

    if (!/^\d{1,20}$/.test(merchantId)) {
      errors.push(`Row ${i}: Merchant Id must be 1–20 digits`);
      rowHasError = true;
    }

    if (!rowHasError) {
      rows.push({
        merchantId,
        rrn,
        date: dateStr,
      });
    }
  }

  return { valid: errors.length === 0, errors, rows };
}
