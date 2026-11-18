<?php
require_once '../includes/auth.php';
requireRole(['doctor']);
require_once '../config/database.php';

$pageTitle = "Prescriptions";

$successMessage = '';
$errorMessage = '';

$patients = [];
$patientAllergies = [];
$dataLoaded = false;

$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$medicineName = trim($_POST['medicine_name'] ?? '');
$dosage = trim($_POST['dosage'] ?? '');
$description = trim($_POST['description'] ?? '');
$needMC = ($_POST['need_mc'] ?? '0') === '1' ? 1 : 0;

function rxEscape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function rxFindColumn($columns, $possibleNames) {

    foreach ($possibleNames as $name) {

        if (in_array($name, $columns, true)) {
            return $name;
        }

    }

    return null;
}

// CSRF PROTECTION

if (empty($_SESSION['rx_csrf'])) {
    $_SESSION['rx_csrf'] = bin2hex(random_bytes(32));
}

// LOAD PATIENTS AND ALLERGIES

try {

    $stmt = $pdo->query(
        "SELECT patient_id, patient_name
         FROM patients
         ORDER BY patient_name ASC"
    );

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query(
        "SELECT patient_id, allergy_name,
                reaction, severity, notes
         FROM patient_allergies
         ORDER BY allergy_name ASC"
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {

        $patientAllergies[$a['patient_id']][] = $a;

    }

    $dataLoaded = true;

} catch (PDOException $e) {

    error_log($e->getMessage());

    $errorMessage = "Unable to load patient or allergy information.";

}

// HANDLE SAVE PRESCRIPTION

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $doctorId = (int)($_SESSION['user_id'] ?? 0);

    if (!hash_equals(
        $_SESSION['rx_csrf'],
        (string)($_POST['csrf_token'] ?? '')
    )) {

        $errorMessage = "Invalid request. Please refresh the page.";

    } elseif (!$dataLoaded) {

        $errorMessage = "Unable to verify patient allergy information.";

    } elseif (
        $patientId <= 0 ||
        $medicineName === '' ||
        $dosage === ''
    ) {

        $errorMessage = "Please complete all required fields.";

    } elseif ($doctorId <= 0) {

        $errorMessage = "Doctor session not found. Please login again.";

    } elseif (($_POST['allergy_reviewed'] ?? '') !== '1') {

        $errorMessage = "Please confirm that you have reviewed the patient's allergy history.";

    } else {

        try {

            // VERIFY PATIENT

            $stmt = $pdo->prepare(
                "SELECT patient_id
                 FROM patients
                 WHERE patient_id = ?"
            );

            $stmt->execute([$patientId]);

            if (!$stmt->fetch()) {

                throw new RuntimeException(
                    "Invalid patient selected."
                );

            }

            // CHECK PATIENT ALLERGIES AGAIN

            $stmt = $pdo->prepare(
                "SELECT allergy_name
                 FROM patient_allergies
                 WHERE patient_id = ?"
            );

            $stmt->execute([$patientId]);

            $allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conflicts = [];

            foreach ($allergies as $a) {

                $allergyName = trim(
                    (string)($a['allergy_name'] ?? '')
                );

                if ($allergyName === '') {
                    continue;
                }

                if (
                    stripos($medicineName, $allergyName) !== false ||
                    stripos($allergyName, $medicineName) !== false
                ) {

                    $conflicts[] = $allergyName;

                }

            }

            if (!empty($conflicts)) {

                throw new RuntimeException(
                    "ALLERGY ALERT! Possible medication conflict: " .
                    implode(', ', $conflicts) .
                    ". Prescription cannot be saved."
                );

            }

            // AUTOMATIC DATABASE COLUMN DETECTION

            $stmt = $pdo->query(
                "SHOW COLUMNS FROM prescriptions"
            );

            $columnDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns = array_column($columnDetails, 'Field');

            $columnMap = [];

            foreach ($columnDetails as $column) {

                $columnMap[$column['Field']] = $column;

            }

            $patientColumn = rxFindColumn(
                $columns,
                ['patient_id']
            );

            $doctorColumn = rxFindColumn(
                $columns,
                ['doctor_id', 'user_id', 'prescribed_by']
            );

            $medicineColumn = rxFindColumn(
                $columns,
                [
                    'medicine_name',
                    'medication_name',
                    'medicine',
                    'medication',
                    'drug_name',
                    'drug'
                ]
            );

            $dosageColumn = rxFindColumn(
                $columns,
                ['dosage', 'dose']
            );

            $descriptionColumn = rxFindColumn(
                $columns,
                [
                    'description',
                    'notes',
                    'doctor_notes',
                    'instructions',
                    'remarks'
                ]
            );

            $mcColumn = rxFindColumn(
                $columns,
                [
                    'need_mc',
                    'mc_required',
                    'requires_mc',
                    'has_mc',
                    'is_mc',
                    'mc_needed',
                    'medical_certificate'
                ]
            );

            $dateColumn = rxFindColumn(
                $columns,
                [
                    'prescription_date',
                    'prescribed_date',
                    'date_prescribed',
                    'created_at',
                    'date'
                ]
            );

            // CHECK REQUIRED COLUMNS

            if (
                !$patientColumn ||
                !$doctorColumn ||
                !$medicineColumn ||
                !$dosageColumn
            ) {

                throw new RuntimeException(
                    "Prescription table columns are incompatible. " .
                    "Available columns: " .
                    implode(', ', $columns)
                );

            }

            if (!$descriptionColumn && $description !== '') {

                throw new RuntimeException(
                    "Doctor's notes column was not found."
                );

            }

            if (!$mcColumn && $needMC === 1) {

                throw new RuntimeException(
                    "Medical certificate column was not found."
                );

            }

            // PREPARE PRESCRIPTION DATA

            $insertData = [
                $patientColumn => $patientId,
                $doctorColumn => $doctorId,
                $medicineColumn => $medicineName,
                $dosageColumn => $dosage
            ];

            if ($descriptionColumn) {

                $insertData[$descriptionColumn] = $description;

            }

            // MEDICAL CERTIFICATE VALUE

            if ($mcColumn) {

                $mcType = $columnMap[$mcColumn]['Type'];

                if (stripos($mcType, 'enum(') === 0) {

                    preg_match_all(
                        "/'([^']*)'/",
                        $mcType,
                        $matches
                    );

                    $allowedValues = $matches[1];

                    $possibleValues = $needMC
                        ? ['yes', '1', 'true', 'required']
                        : ['no', '0', 'false', 'not required'];

                    $mcValue = null;

                    foreach ($allowedValues as $allowed) {

                        if (in_array(
                            strtolower($allowed),
                            $possibleValues,
                            true
                        )) {

                            $mcValue = $allowed;
                            break;

                        }

                    }

                    if ($mcValue === null) {

                        throw new RuntimeException(
                            "Unsupported medical certificate value."
                        );

                    }

                    $insertData[$mcColumn] = $mcValue;

                } elseif (
                    stripos($mcType, 'varchar') === 0 ||
                    stripos($mcType, 'char') === 0
                ) {

                    $insertData[$mcColumn] =
                        $needMC ? 'Yes' : 'No';

                } else {

                    $insertData[$mcColumn] = $needMC;

                }

            }

            // PRESCRIPTION DATE

            if ($dateColumn) {

                $dateType = strtolower(
                    $columnMap[$dateColumn]['Type']
                );

                if (
                    strpos($dateType, 'datetime') === 0 ||
                    strpos($dateType, 'timestamp') === 0
                ) {

                    $insertData[$dateColumn] =
                        date('Y-m-d H:i:s');

                } else {

                    $insertData[$dateColumn] =
                        date('Y-m-d');

                }

            }

            // CHECK OTHER REQUIRED DATABASE FIELDS

            foreach ($columnDetails as $column) {

                $field = $column['Field'];

                $extra = strtolower($column['Extra'] ?? '');

                if (
                    $column['Null'] === 'NO' &&
                    $column['Default'] === null &&
                    strpos($extra, 'auto_increment') === false &&
                    strpos($extra, 'generated') === false &&
                    !array_key_exists($field, $insertData)
                ) {

                    throw new RuntimeException(
                        "Additional required database field: " .
                        $field
                    );

                }

            }

            // BUILD INSERT QUERY AUTOMATICALLY

            $insertColumns = array_keys($insertData);

            $safeColumns = array_map(
                function($column) {

                    return '`' .
                        str_replace('`', '``', $column) .
                        '`';

                },
                $insertColumns
            );

            $placeholders = implode(
                ', ',
                array_fill(0, count($insertData), '?')
            );

            $sql = "INSERT INTO prescriptions (" .
                   implode(', ', $safeColumns) .
                   ") VALUES (" .
                   $placeholders .
                   ")";

            $stmt = $pdo->prepare($sql);

            $stmt->execute(array_values($insertData));

            // SUCCESS MESSAGE

            $_SESSION['rx_success'] =
                "Prescription saved successfully.";

            header("Location: prescriptions.php");
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            // DETAILED ERROR FOR LOCALHOST DEVELOPMENT

            $errorMessage =
                "Database error: " . $e->getMessage();

        } catch (RuntimeException $e) {

            $errorMessage = $e->getMessage();

        }

    }

}

// LOAD SUCCESS MESSAGE

if (isset($_SESSION['rx_success'])) {

    $successMessage = $_SESSION['rx_success'];

    unset($_SESSION['rx_success']);

}

// CONVERT ALLERGY DATA TO JSON

$allergyJson = json_encode(
    (object)$patientAllergies,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
);

require_once '../includes/header.php';
?>

<style>

.prescription-page .allergy-alert-box {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-left: 5px solid #dc3545;
    border-radius: 9px;
    padding: 20px;
    margin-bottom: 20px;
}

.prescription-page .allergy-alert-title {
    color: #b91c1c;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 10px;
}

.prescription-page .allergy-item {
    background: #ffffff;
    border: 1px solid #f1d1d1;
    border-radius: 8px;
    padding: 15px;
    margin-top: 12px;
}

.prescription-page .allergy-item h6 {
    color: #b91c1c;
    font-weight: 700;
    margin-bottom: 10px;
}

.prescription-page .allergy-item p {
    font-size: 14px;
    margin-bottom: 6px;
    color: #555;
}

.prescription-page .severity-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
}

.prescription-page .severity-severe {
    background: #dc3545;
    color: #fff;
}

.prescription-page .severity-moderate {
    background: #fff0cd;
    color: #946200;
}

.prescription-page .severity-mild {
    background: #e2f3e8;
    color: #237a41;
}

.prescription-page .severity-unknown {
    background: #eeeeee;
    color: #666666;
}

.prescription-page .no-allergy-box {
    background: #fff8e5;
    border: 1px solid #ffe2a1;
    border-left: 5px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    color: #856404;
    font-size: 14px;
}

.prescription-page .medicine-conflict {
    background: #f8d7da;
    color: #842029;
    border: 1px solid #f5c2c7;
    border-radius: 8px;
    padding: 15px;
    margin-top: 12px;
    font-size: 14px;
}

.prescription-page .medicine-checked {
    background: #f0f4f7;
    color: #495057;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    margin-top: 12px;
    font-size: 13px;
}

.prescription-page .review-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
}

.prescription-page .form-label {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
}

.prescription-page .form-control,
.prescription-page .form-select {
    border-radius: 7px;
}

.prescription-page .save-btn {
    padding: 10px 22px;
    border-radius: 7px;
}

.prescription-page .save-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

</style>

<div class="prescription-page">

    <!-- SUCCESS MESSAGE -->

    <?php if ($successMessage): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= rxEscape($successMessage) ?>

        </div>

    <?php endif; ?>

    <!-- ERROR MESSAGE -->

    <?php if ($errorMessage): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= rxEscape($errorMessage) ?>

        </div>

    <?php endif; ?>

    <div class="section-card mb-4">

        <h4>New Prescription</h4>

        <p class="text-muted mb-4">

            Record medicine and medical certificate information.

        </p>

        <form method="POST"
              action="prescriptions.php"
              id="prescriptionForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= rxEscape($_SESSION['rx_csrf']) ?>"
            >

            <!-- PATIENT NAME -->

            <div class="mb-3">

                <label class="form-label" for="patient_id">

                    Patient Name
                    <span class="text-danger">*</span>

                </label>

                <select
                    name="patient_id"
                    id="patient_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        -- Select Patient --
                    </option>

                    <?php foreach ($patients as $p): ?>

                        <option
                            value="<?= (int)$p['patient_id'] ?>"
                            <?= $patientId === (int)$p['patient_id']
                                ? 'selected' : '' ?>
                        >

                            <?= rxEscape($p['patient_name']) ?>

                            <?= !empty(
                                $patientAllergies[$p['patient_id']]
                            ) ? ' - Allergy Alert' : '' ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- PATIENT ALLERGY ALERT -->

            <div
                id="allergyAlertContainer"
                aria-live="polite"
                hidden
            ></div>

            <!-- MEDICINE NAME -->

            <div class="mb-3">

                <label class="form-label" for="medicine_name">

                    Medicine Name
                    <span class="text-danger">*</span>

                </label>

                <input
                    type="text"
                    name="medicine_name"
                    id="medicine_name"
                    class="form-control"
                    placeholder="Enter medicine name"
                    value="<?= rxEscape($medicineName) ?>"
                    autocomplete="off"
                    required
                >

                <!-- MEDICINE ALLERGY CONFLICT -->

                <div
                    id="medicineConflict"
                    class="medicine-conflict"
                    role="alert"
                    hidden
                >

                    <strong>

                        <i class="bi bi-exclamation-triangle-fill me-1"></i>

                        MEDICATION ALLERGY ALERT!

                    </strong>

                    <p
                        class="mb-0 mt-2"
                        id="conflictMessage"
                    ></p>

                </div>

                <div
                    id="medicineChecked"
                    class="medicine-checked"
                    hidden
                >

                    <i class="bi bi-info-circle-fill me-1"></i>

                    No direct allergy name match detected.
                    Please verify medication ingredients and
                    patient allergy history.

                </div>

            </div>

            <!-- DOSAGE -->

            <div class="mb-3">

                <label class="form-label" for="dosage">

                    Dosage
                    <span class="text-danger">*</span>

                </label>

                <input
                    type="text"
                    name="dosage"
                    id="dosage"
                    class="form-control"
                    placeholder="Enter dosage"
                    value="<?= rxEscape($dosage) ?>"
                    required
                >

            </div>

            <!-- DOCTOR NOTES -->

            <div class="mb-3">

                <label class="form-label" for="description">

                    Description / Doctor's Notes

                </label>

                <textarea
                    name="description"
                    id="description"
                    class="form-control"
                    rows="4"
                    placeholder="Enter doctor's notes or medication instructions"
                ><?= rxEscape($description) ?></textarea>

            </div>

            <!-- MEDICAL CERTIFICATE -->

            <div class="mb-4">

                <label class="form-label" for="need_mc">

                    Does the patient need an MC?

                </label>

                <select
                    name="need_mc"
                    id="need_mc"
                    class="form-select"
                >

                    <option
                        value="0"
                        <?= $needMC === 0 ? 'selected' : '' ?>
                    >

                        No

                    </option>

                    <option
                        value="1"
                        <?= $needMC === 1 ? 'selected' : '' ?>
                    >

                        Yes

                    </option>

                </select>

            </div>

            <!-- ALLERGY CONFIRMATION -->

            <div class="review-box mb-4">

                <div class="form-check">

                    <input
                        type="checkbox"
                        name="allergy_reviewed"
                        id="allergyReviewed"
                        value="1"
                        class="form-check-input"
                        required
                    >

                    <label
                        class="form-check-label"
                        for="allergyReviewed"
                    >

                        I have reviewed the patient's allergy history
                        and verified the prescribed medication.

                    </label>

                </div>

            </div>

            <!-- SAVE BUTTON -->

            <button
                type="submit"
                id="savePrescriptionBtn"
                class="btn btn-danger save-btn"
                disabled
            >

                <i class="bi bi-save me-2"></i>

                Save Prescription

            </button>

            <noscript>

                <div class="alert alert-warning mt-3">

                    Please enable JavaScript to use the
                    prescription allergy alert feature.

                </div>

            </noscript>

        </form>

    </div>

</div>

<script>

const patientAllergies = <?= $allergyJson ?: '{}' ?>;

const allergyDataLoaded = <?= $dataLoaded ? 'true' : 'false' ?>;

const patientSelect =
    document.getElementById('patient_id');

const medicineInput =
    document.getElementById('medicine_name');

const dosageInput =
    document.getElementById('dosage');

const allergyContainer =
    document.getElementById('allergyAlertContainer');

const medicineConflict =
    document.getElementById('medicineConflict');

const medicineChecked =
    document.getElementById('medicineChecked');

const conflictMessage =
    document.getElementById('conflictMessage');

const saveButton =
    document.getElementById('savePrescriptionBtn');

const prescriptionForm =
    document.getElementById('prescriptionForm');

const allergyReviewed =
    document.getElementById('allergyReviewed');

// ESCAPE HTML

function escapeHtml(value) {

    return String(value ?? '').replace(
        /[&<>"']/g,
        function(char) {

            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return map[char];

        }
    );

}

// GET PATIENT ALLERGIES

function getPatientAllergies() {

    return patientAllergies[patientSelect.value] || [];

}

// SEVERITY BADGES

function getSeverityClass(severity) {

    severity = String(severity || '')
        .toLowerCase()
        .trim();

    if (['severe', 'high', 'critical'].includes(severity)) {

        return 'severity-severe';

    }

    if (severity === 'moderate') {

        return 'severity-moderate';

    }

    if (['mild', 'low'].includes(severity)) {

        return 'severity-mild';

    }

    return 'severity-unknown';

}

// AUTOMATIC PATIENT ALLERGY ALERT

function displayAllergyAlert() {

    const patientId = patientSelect.value;

    const allergies = getPatientAllergies();

    allergyContainer.innerHTML = '';

    if (!patientId) {

        allergyContainer.hidden = true;

        checkMedicineAllergy();

        return;

    }

    allergyContainer.hidden = false;

    if (!allergyDataLoaded) {

        allergyContainer.innerHTML = `
            <div class="alert alert-danger">
                Unable to verify allergy information.
                Prescription cannot be saved.
            </div>
        `;

        checkMedicineAllergy();

        return;

    }

    if (allergies.length === 0) {

        allergyContainer.innerHTML = `

            <div class="no-allergy-box">

                <strong>

                    <i class="bi bi-info-circle-fill me-1"></i>

                    No Recorded Allergy

                </strong>

                <div class="mt-1">

                    No allergy information has been recorded.
                    Please confirm the patient's allergy history
                    before prescribing medication.

                </div>

            </div>

        `;

    } else {

        let html = `

            <div class="allergy-alert-box">

                <div class="allergy-alert-title">

                    <i class="bi bi-exclamation-triangle-fill me-2"></i>

                    PATIENT ALLERGY ALERT!

                </div>

                <p class="text-danger small mb-3">

                    This patient has recorded allergies.
                    Please review the allergy information
                    before prescribing medication.

                </p>

        `;

        allergies.forEach(function(allergy) {

            const severityClass =
                getSeverityClass(allergy.severity);

            html += `

                <div class="allergy-item">

                    <h6>

                        <i class="bi bi-exclamation-circle-fill me-1"></i>

                        ${escapeHtml(
                            allergy.allergy_name || 'Unknown'
                        )}

                    </h6>

                    <p>

                        <strong>Reaction:</strong>

                        ${escapeHtml(
                            allergy.reaction || 'Not specified'
                        )}

                    </p>

                    <p>

                        <strong>Severity:</strong>

                        <span class="severity-badge ${severityClass}">

                            ${escapeHtml(
                                allergy.severity || 'Unknown'
                            )}

                        </span>

                    </p>

                    <p class="mb-0">

                        <strong>Notes:</strong>

                        ${escapeHtml(allergy.notes || '-')}

                    </p>

                </div>

            `;

            if (severityClass === 'severity-severe') {

                html += `

                    <div class="alert alert-danger mt-3 mb-0">

                        <i class="bi bi-exclamation-triangle-fill me-1"></i>

                        <strong>Severe Allergy Warning!</strong>

                        Review this allergy carefully before
                        prescribing medication.

                    </div>

                `;

            }

        });

        html += '</div>';

        allergyContainer.innerHTML = html;

    }

    checkMedicineAllergy();

}

// REAL-TIME MEDICATION ALLERGY CHECK

function checkMedicineAllergy() {

    const medication =
        medicineInput.value.toLowerCase().trim();

    const allergies = getPatientAllergies();

    medicineConflict.hidden = true;
    medicineChecked.hidden = true;

    medicineInput.classList.remove('is-invalid');

    let conflicts = [];

    if (patientSelect.value && medication) {

        allergies.forEach(function(allergy) {

            const allergyName = String(
                allergy.allergy_name || ''
            ).toLowerCase().trim();

            if (!allergyName) {
                return;
            }

            if (
                medication.includes(allergyName) ||
                allergyName.includes(medication)
            ) {

                conflicts.push(allergy.allergy_name);

            }

        });

    }

    const hasConflict = conflicts.length > 0;

    if (hasConflict) {

        medicineConflict.hidden = false;

        conflictMessage.textContent =
            'Possible medication allergy conflict: ' +
            conflicts.join(', ') +
            '. Prescription cannot be saved.';

        medicineInput.classList.add('is-invalid');

    } else if (
        allergyDataLoaded &&
        medication &&
        patientSelect.value &&
        allergies.length > 0
    ) {

        medicineChecked.hidden = false;

    }

    saveButton.disabled =
        !allergyDataLoaded ||
        !patientSelect.value ||
        !medication ||
        !dosageInput.value.trim() ||
        !allergyReviewed.checked ||
        hasConflict;

    if (hasConflict) {

        saveButton.innerHTML = `

            <i class="bi bi-lock-fill me-2"></i>

            Prescription Blocked

        `;

    } else {

        saveButton.innerHTML = `

            <i class="bi bi-save me-2"></i>

            Save Prescription

        `;

    }

    return !hasConflict;

}

// EVENT LISTENERS

patientSelect.addEventListener('change', function() {

    allergyReviewed.checked = false;

    displayAllergyAlert();

});

medicineInput.addEventListener('input', function() {

    allergyReviewed.checked = false;

    checkMedicineAllergy();

});

dosageInput.addEventListener(
    'input',
    checkMedicineAllergy
);

allergyReviewed.addEventListener(
    'change',
    checkMedicineAllergy
);

prescriptionForm.addEventListener('submit', function(event) {

    if (!checkMedicineAllergy() || saveButton.disabled) {

        event.preventDefault();

    }

});

displayAllergyAlert();

</script>

<?php require_once '../includes/footer.php'; ?>