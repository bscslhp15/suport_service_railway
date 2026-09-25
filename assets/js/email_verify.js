document.addEventListener('DOMContentLoaded', function () {
    if (!window.emailVerifyData || !window.emailVerifyData.email) {
        return;
    }

    if (typeof emailjs === 'undefined') {
        console.error('EmailJS SDK not loaded.');
        alert('EmailJS could not be loaded. Please check your internet connection or script source.');
        return;
    }

    emailjs.init(window.emailVerifyData.publicKey);

    const email = window.emailVerifyData.email;
    const name = window.emailVerifyData.name || '';
    const code = window.emailVerifyData.code;
    const expiresAt = window.emailVerifyData.expires ? new Date(window.emailVerifyData.expires * 1000) : null;
    const sendOnLoad = window.emailVerifyData.sendOnLoad;
    const resendButton = document.getElementById('resendCodeBtn');
    const countdownElement = document.getElementById('verificationCountdown');
    const codeInputs = Array.from(document.querySelectorAll('.code-digit'));
    const codeInputHidden = document.getElementById('codeInput');
    const verifyForm = document.getElementById('verifyForm');
    const submitButton = verifyForm.querySelector('button[type="submit"]');

    let isRefreshing = false;
    let countdownInterval = null;

    const EMAIL_RATE_LIMIT_KEY = `email_send_limit_${email}`;
    const EMAIL_RATE_LIMIT_MINUTES = 10;

    const canSendEmail = function () {
        const lastSendTime = localStorage.getItem(EMAIL_RATE_LIMIT_KEY);
        if (!lastSendTime) {
            return true;
        }
        const timeSinceLastSend = Date.now() - parseInt(lastSendTime, 10);
        const minutesPassed = timeSinceLastSend / (1000 * 60);
        return minutesPassed >= EMAIL_RATE_LIMIT_MINUTES;
    };

    const recordEmailSent = function () {
        localStorage.setItem(EMAIL_RATE_LIMIT_KEY, Date.now().toString());
    };

    const formatTime = function (seconds) {
        const minutes = Math.floor(seconds / 60);
        const remaining = seconds % 60;
        return `${minutes.toString().padStart(2, '0')}:${remaining.toString().padStart(2, '0')}`;
    };

    const refreshVerificationCode = function () {
        if (isRefreshing) {
            return;
        }
        isRefreshing = true;
        countdownElement.textContent = 'Verification code expired. Requesting a new one...';
        resendButton.disabled = true;
        submitButton.disabled = true;

        const url = new URL(window.location.href);
        url.searchParams.set('action', 'refresh');
        url.searchParams.set('send', '1');
        window.location.href = url.toString();
    };

    const updateCountdown = function () {
        if (!expiresAt) {
            countdownElement.textContent = 'Verification countdown unavailable.';
            resendButton.disabled = true;
            return;
        }

        const now = new Date();
        const remainingMs = expiresAt.getTime() - now.getTime();

        if (remainingMs <= 0) {
            clearInterval(countdownInterval);
            countdownElement.textContent = 'Verification code expired.';
            resendButton.textContent = 'Resend verification code';
            resendButton.disabled = false;
            submitButton.disabled = true;
            return;
        }

        const remainingSeconds = Math.floor(remainingMs / 1000);
        countdownElement.textContent = `Code expires in ${formatTime(remainingSeconds)}`;
        resendButton.textContent = `Resend available in ${formatTime(remainingSeconds)}`;
        resendButton.disabled = true;
    };

    const sendVerificationEmail = function () {
        if (!email || !code) {
            return;
        }

        if (!canSendEmail()) {
            alert('You can only send one email every 10 minutes. Please try again later.');
            return;
        }

        resendButton.textContent = 'Sending...';
        resendButton.disabled = true;

        emailjs.send(window.emailVerifyData.serviceId, window.emailVerifyData.templateId, {
            user_email: email,
            to_email: email,
            email: email,
            name: name,
            otp_code: code
        }).then(function () {
            recordEmailSent();
            resendButton.textContent = 'Resend verification email';
            resendButton.disabled = false;

            // Show success message instead of alert
            const successMessage = document.getElementById('emailSentMessage');
            if (successMessage) {
                successMessage.style.display = 'block';
                // Hide the message after 5 seconds
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 5000);
            }
        }, function (error) {
            console.error('EmailJS error:', error);
            resendButton.textContent = 'Resend verification email';
            resendButton.disabled = false;
            alert('Unable to send the code. Please check your network or template settings.');
        });
    };

    codeInputs.forEach(function (input, index) {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 1) {
                this.value = this.value.slice(-1);
            }
            if (this.value && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace' && !this.value && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
    });

    verifyForm.addEventListener('submit', function (event) {
        const combined = codeInputs.map(function (input) {
            return input.value.trim();
        }).join('');

        if (combined.length !== 6) {
            event.preventDefault();
            alert('Please enter the full 6-digit verification code.');
            return;
        }

        codeInputHidden.value = combined;
    });

    resendButton.addEventListener('click', function () {
        if (expiresAt && new Date() < expiresAt) {
            alert('Code is still valid. Wait for it to expire before requesting a new one.');
            return;
        }
        refreshVerificationCode();
    });

    if (sendOnLoad) {
        if (canSendEmail()) {
            sendVerificationEmail();
        }
        const url = new URL(window.location.href);
        url.searchParams.delete('send');
        url.searchParams.delete('action');
        window.history.replaceState({}, '', url.toString());
    }
    if (expiresAt) {
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000);
    }
});
