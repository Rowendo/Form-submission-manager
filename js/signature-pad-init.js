document.addEventListener('DOMContentLoaded', () => {
    // Initialize the signature pad
    const canvas = document.getElementById('signature-pad');
    if (!canvas) {
        console.error('Signature pad canvas not found.');
        return;
    }

    // Create a new instance of SignaturePad
    const signaturePad = new SignaturePad(canvas);
    const clearButton = document.getElementById('clear-signature');
    const signatureInput = document.getElementById('handtekening-data');

    // Adjust canvas size to fit the container
    const resizeCanvas = () => {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear(); // Clear the canvas when resizing
    };

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas(); // Call resize on load

    // Clear the signature pad when the "Clear" button is clicked
    clearButton?.addEventListener('click', () => {
        signaturePad.clear();
    });

    // Save the signature data before form submission
    const form = canvas.closest('form');
    form?.addEventListener('submit', (e) => {
        if (signaturePad.isEmpty()) {
            e.preventDefault();
            alert('Please provide your signature.');
        } else {
            signatureInput.value = signaturePad.toDataURL(); // Convert signature to Base64 string
        }
    });
});