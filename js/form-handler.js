// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function () {
    // Cache DOM elements
    const form = document.getElementById('test-submission-form');
    const debugOutput = document.getElementById('debug-output');
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;
    const canvas = document.getElementById('signature-pad');
    const clearButton = document.getElementById('clear-signature');
    const signatureInput = document.getElementById('handtekening-data');

    let signaturePad = null;

    // Debug logging function
    function debugLog(message, data) {
        const timestamp = new Date().toTimeString().split(' ')[0];
        const logMessage = `[${timestamp}] ${message} ${data ? JSON.stringify(data) : ''}`;
        console.log(logMessage);
        if (debugOutput) {
            debugOutput.textContent += logMessage + '\n';
        }
    }

    // Initialize the signature pad
    function initializeSignaturePad() {
        if (canvas) {
            signaturePad = new SignaturePad(canvas);

            // Resize the canvas for high-resolution devices
            const resizeCanvas = () => {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                signaturePad.clear();
            };
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            // Clear signature button
            clearButton?.addEventListener('click', () => {
                signaturePad.clear();
            });
        } else {
            debugLog('Error', 'Signature pad canvas not found.');
        }
    }

    // Form validation function
    function validateForm(formData) {
        const name = formData.get('naam');
        if (!name || name.trim() === '') {
            return 'Name is required';
        }
        if (signaturePad && signaturePad.isEmpty()) {
            return 'Signature is required';
        }
        return null; // null means validation passed
    }

    // Disable form while submitting
    function setFormSubmitting(isSubmitting) {
        if (submitButton) {
            submitButton.disabled = isSubmitting;
            submitButton.textContent = isSubmitting ? 'Submitting...' : 'Submit';
        }
    }

    // Handle form submission
    if (form) {
        debugLog('Form handler initialized');

        // Initialize signature pad
        initializeSignaturePad();

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Create FormData object
            const formData = new FormData(form);

            // Add signature data if applicable
            if (signaturePad && !signaturePad.isEmpty()) {
                formData.append('handtekening', signaturePad.toDataURL());
            }

            // Validate form
            const validationError = validateForm(formData);
            if (validationError) {
                debugLog('Validation error', validationError);
                alert(validationError);
                return;
            }

            // Add required WordPress data
            formData.append('action', 'submit_form');
            formData.append('nonce', fsmData.nonce);

            debugLog('Submitting form data', Object.fromEntries(formData));

            // Disable form while submitting
            setFormSubmitting(true);

            // Submit the form
            fetch(fsmData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then((data) => {
                    debugLog('Server response', data);
                    if (data.success) {
                        alert('Thank you! Your name has been submitted successfully.');
                        form.reset();
                        if (signaturePad) {
                            signaturePad.clear();
                        }
                    } else {
                        throw new Error(data.data || 'Submission failed');
                    }
                })
                .catch((error) => {
                    debugLog('Error', error.message);
                    alert('Error submitting form: ' + error.message);
                })
                .finally(() => {
                    setFormSubmitting(false);
                });
        });

        // Add input validation
        const nameInput = form.querySelector('input[name="naam"]');
        if (nameInput) {
            nameInput.addEventListener('input', function () {
                const isEmpty = !this.value.trim();
                this.setCustomValidity(isEmpty ? 'Please enter your name' : '');
                debugLog('Input validation', { field: 'name', isEmpty });
            });
        }
    } else {
        debugLog('Error', 'Form not found on page');
    }

    // Check if WordPress data is available
    if (typeof fsmData === 'undefined') {
        debugLog('Error', 'WordPress data not properly loaded');
    } else {
        debugLog('WordPress data loaded', {
            ajaxUrl: fsmData.ajaxUrl ? 'exists' : 'missing',
            nonce: fsmData.nonce ? 'exists' : 'missing',
        });
    }
});