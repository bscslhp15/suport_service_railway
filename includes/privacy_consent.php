<style>
    .privacy-consent-modal {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, 0.68);
    }
    .privacy-consent-modal.is-open {
        display: flex;
    }
    .privacy-consent-dialog {
        box-sizing: border-box;
        width: min(680px, 100%);
        max-height: min(760px, calc(100vh - 40px));
        overflow-y: auto;
        padding: 28px;
        border-radius: 18px;
        background: #ffffff;
        color: #1f2937;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
    }
    .privacy-consent-dialog h2 {
        margin: 0 0 8px;
        color: #800000;
        font-size: 1.45rem;
    }
    .privacy-consent-dialog > p {
        margin: 0 0 18px;
        color: #475569;
        line-height: 1.55;
    }
    .privacy-consent-section {
        margin: 14px 0;
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
    }
    .privacy-consent-section h3 {
        margin: 0 0 7px;
        color: #111827;
        font-size: 1rem;
    }
    .privacy-consent-section p {
        margin: 0;
        color: #475569;
        font-size: 0.9rem;
        line-height: 1.55;
    }
    .privacy-consent-check {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 12px;
        color: #1f2937;
        font-size: 0.92rem;
        line-height: 1.45;
        cursor: pointer;
    }
    .privacy-consent-check input {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        margin-top: 2px;
        accent-color: #800000;
    }
    .privacy-consent-error {
        min-height: 20px;
        margin: 12px 0 0;
        color: #b91c1c;
        font-size: 0.88rem;
    }
    .privacy-consent-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
    .privacy-consent-actions button {
        min-height: 42px;
        padding: 10px 17px;
        border: 0;
        border-radius: 9px;
        cursor: pointer;
        font-weight: 700;
    }
    .privacy-consent-cancel {
        background: #e2e8f0;
        color: #334155;
    }
    .privacy-consent-accept {
        background: #800000;
        color: #ffffff;
    }
    @media (max-width: 520px) {
        .privacy-consent-modal {
            padding: 12px;
        }
        .privacy-consent-dialog {
            max-height: calc(100vh - 24px);
            padding: 21px;
        }
        .privacy-consent-actions {
            flex-direction: column-reverse;
        }
        .privacy-consent-actions button {
            width: 100%;
        }
    }
</style>

<div class="privacy-consent-modal" id="privacyConsentModal" data-open="<?= !empty($consentError) ? 'true' : 'false' ?>" role="dialog" aria-modal="true" aria-labelledby="privacyConsentTitle">
    <div class="privacy-consent-dialog">
        <h2 id="privacyConsentTitle">Agreement and Privacy Policy</h2>
        <p>Please review and accept both items before requesting an account.</p>

        <section class="privacy-consent-section">
            <h3>1. Agreement / Data Privacy Consent</h3>
            <p>I agree to provide accurate information and use the PASS Support Service System responsibly. In accordance with the Data Privacy Act of 2012 (Republic Act No. 10173), I consent to the collection and processing of my registration details, identification documents, and account information for account verification, approval, authentication, and delivery of school services.</p>
            <label class="privacy-consent-check">
                <input type="checkbox" id="agreementConsentCheck">
                <span>I have read and accept the Agreement and Data Privacy Consent.</span>
            </label>
        </section>

        <section class="privacy-consent-section">
            <h3>2. Privacy Policy</h3>
            <p>Your information will be accessed only by authorized school personnel for legitimate school and system purposes. We will apply reasonable safeguards and retain information only as long as necessary or required by school policy and applicable law. You may contact the school regarding your personal information and privacy concerns.</p>
            <label class="privacy-consent-check">
                <input type="checkbox" id="privacyConsentCheck">
                <span>I have read and accept the Privacy Policy.</span>
            </label>
        </section>

        <p class="privacy-consent-error" id="privacyConsentError" role="alert"></p>
        <div class="privacy-consent-actions">
            <button type="button" class="privacy-consent-cancel" id="privacyConsentCancel">Cancel</button>
            <button type="button" class="privacy-consent-accept" id="privacyConsentAccept">I Accept and Continue</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('privacyConsentModal');
        const agreementCheck = document.getElementById('agreementConsentCheck');
        const privacyCheck = document.getElementById('privacyConsentCheck');
        const error = document.getElementById('privacyConsentError');
        const registrationForms = document.querySelectorAll('form.registration-form');
        const registerTriggers = document.querySelectorAll('#show-register');
        const cancelButton = document.getElementById('privacyConsentCancel');
        const acceptButton = document.getElementById('privacyConsentAccept');
        const consentStorageKey = 'passPrivacyConsent:' + window.location.pathname;
        const consentPendingKey = consentStorageKey + ':pending';
        const agreementStorageKey = consentStorageKey + ':agreement';
        const privacyStorageKey = consentStorageKey + ':privacy';

        if (!modal) return;

        const openModal = function () {
            localStorage.setItem(consentPendingKey, '1');
            modal.classList.add('is-open');
            document.body.classList.add('privacy-consent-open');
            agreementCheck.focus();
        };
        const closeModal = function () {
            modal.classList.remove('is-open');
            document.body.classList.remove('privacy-consent-open');
            error.textContent = '';
        };

        registerTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                localStorage.removeItem(consentStorageKey + ':accepted');
                openModal();
            });
        });
        cancelButton.addEventListener('click', function () {
            localStorage.removeItem(consentPendingKey);
            localStorage.removeItem(agreementStorageKey);
            localStorage.removeItem(privacyStorageKey);
            window.location.href = window.location.pathname;
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
        acceptButton.addEventListener('click', function () {
            if (!agreementCheck.checked || !privacyCheck.checked) {
                error.textContent = 'Please accept both the Agreement and Privacy Policy to continue.';
                return;
            }
            registrationForms.forEach(function (form) {
                form.querySelector('input[name="agreement_consent"]').value = '1';
                form.querySelector('input[name="privacy_consent"]').value = '1';
            });
            localStorage.removeItem(consentPendingKey);
            localStorage.removeItem(agreementStorageKey);
            localStorage.removeItem(privacyStorageKey);
            localStorage.setItem(consentStorageKey + ':accepted', '1');
            closeModal();
        });

        agreementCheck.checked = localStorage.getItem(agreementStorageKey) === '1';
        privacyCheck.checked = localStorage.getItem(privacyStorageKey) === '1';
        agreementCheck.addEventListener('change', function () {
            localStorage.setItem(agreementStorageKey, agreementCheck.checked ? '1' : '0');
        });
        privacyCheck.addEventListener('change', function () {
            localStorage.setItem(privacyStorageKey, privacyCheck.checked ? '1' : '0');
        });

        const isRegistrationMode = new URLSearchParams(window.location.search).get('mode') === 'register';
        const shouldRestoreModal = modal.dataset.open === 'true'
            || (isRegistrationMode && localStorage.getItem(consentStorageKey + ':accepted') !== '1');
        if (shouldRestoreModal) openModal();
    });
</script>
