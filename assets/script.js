document.addEventListener('DOMContentLoaded', function() {
    /**
     * Initializes a single QR Code Generator instance.
     * This allows multiple generators on the same page without conflicts.
     * @param {HTMLElement} generatorWrapper - The main container element for a generator instance.
     */
    function initializeQrGenerator(generatorWrapper) {
        const generateBtn = generatorWrapper.querySelector('.mqrg-generate-btn');
        if (!generateBtn) return; // Exit if the essential button isn't found

        // Get all interactive elements within this specific generator instance
        const urlInput = generatorWrapper.querySelector('.mqrg-input');
        const qrContainer = generatorWrapper.querySelector('.mqrg-qrcode-container');
        const resultActions = generatorWrapper.querySelector('.mqrg-result-actions');
        const shortlinkOutput = generatorWrapper.querySelector('.mqrg-shortlink-output');
        const copyBtn = generatorWrapper.querySelector('.mqrg-copy-btn');
        const downloadBtn = generatorWrapper.querySelector('.mqrg-download-btn');
        const copyFeedback = generatorWrapper.querySelector('.mqrg-copy-feedback');
        const spinner = generatorWrapper.querySelector('.spinner');
        const colorDots = generatorWrapper.querySelectorAll('.mqrg-color-dot');

        // State variables for this instance
        let qrcode = null;
        let currentQrText = '';
        let currentQrColor = '#000000';

        /**
         * Generates or updates the QR code image.
         */
        function generateQRCode() {
            if (!currentQrText) return;
            qrContainer.innerHTML = ''; // Clear previous QR code or placeholder
            qrcode = new QRCode(qrContainer, {
                text: currentQrText,
                width: 256,
                height: 256,
                colorDark: currentQrColor,
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
            resultActions.style.display = 'block'; // Show the result actions
        }

        /**
         * Handles the AJAX call to the backend to generate the shortlink.
         */
        function handleGeneration() {
            const longUrl = urlInput.value.trim();
            if (!longUrl || !longUrl.includes('.')) { // Simple URL validation
                alert('Vui lòng nhập một đường dẫn hợp lệ.');
                return;
            }
            spinner.classList.add('is-active');
            generateBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'mqrg_generate_link');
            formData.append('nonce', mqrg_data.nonce); // mqrg_data is localized from PHP
            formData.append('long_url', longUrl);

            fetch(mqrg_data.ajax_url, { 
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentQrText = data.data.short_url;
                    shortlinkOutput.value = data.data.short_url;
                    generateQRCode();
                } else {
                    alert('Lỗi: ' + data.data.message);
                    qrContainer.innerHTML = '<p class="mqrg-placeholder-text">Đã xảy ra lỗi. Vui lòng thử lại.</p>';
                    resultActions.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Đã có lỗi xảy ra. Vui lòng kiểm tra console.');
            })
            .finally(() => {
                spinner.classList.remove('is-active');
                generateBtn.disabled = false;
            });
        }

        // --- Event Listeners ---

        // Generate on button click
        generateBtn.addEventListener('click', handleGeneration);

        // Generate on pressing Enter in the input field
        urlInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleGeneration();
            }
        });

        // Handle color selection
        colorDots.forEach(dot => {
            dot.addEventListener('click', function() {
                generatorWrapper.querySelectorAll('.mqrg-color-dot').forEach(d => d.classList.remove('active'));
                this.classList.add('active');
                currentQrColor = this.dataset.color;
                // Regenerate QR code with new color if one already exists
                if (qrcode) {
                    generateQRCode();
                }
            });
        });

        // Handle copy button click
        copyBtn.addEventListener('click', function() {
            shortlinkOutput.select();
            document.execCommand('copy');
            copyFeedback.style.display = 'block';
            setTimeout(() => { copyFeedback.style.display = 'none'; }, 2000);
        });

        // Handle download button click
        downloadBtn.addEventListener('click', function() {
            const originalCanvas = qrContainer.querySelector('canvas');
            
            if (!originalCanvas) {
                // Fallback for img tag if canvas is not found
                const img = qrContainer.querySelector('img');
                if (img) {
                    const link = document.createElement('a');
                    link.href = img.src;
                    link.download = 'qrcode.png';
                    link.click();
                }
                return;
            }

            // Create a new canvas with padding to act as a white border
            const padding = 15; // The width of the white border, adjusted for a better look.
            const newCanvas = document.createElement('canvas');
            const context = newCanvas.getContext('2d');

            // Set the new canvas dimensions to include the padding
            newCanvas.width = originalCanvas.width + padding * 2;
            newCanvas.height = originalCanvas.height + padding * 2;

            // Fill the new canvas with a white background
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, newCanvas.width, newCanvas.height);

            // Draw the original QR code canvas onto the new canvas, centered
            context.drawImage(originalCanvas, padding, padding);

            // Create a download link from the new, padded canvas
            const link = document.createElement('a');
            link.download = 'qrcode.png';
            link.href = newCanvas.toDataURL('image/png');
            link.click();
        });
    }

    // Initialize all generator instances found on the page
    document.querySelectorAll('.mqrg-wrap').forEach(initializeQrGenerator);
});
