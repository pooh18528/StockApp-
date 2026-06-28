    </main>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Reusable Image Dropzone (Drag & Drop + Client Compression)
        window.initImageDropzone = function(dropzoneId, inputId, previewContainerId, previewImgId, removeBtnId) {
            const dropzone = document.getElementById(dropzoneId);
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewContainerId);
            const previewImg = document.getElementById(previewImgId);
            const removeBtn = document.getElementById(removeBtnId);

            if (!dropzone || !input) return;

            // Click to open file browser
            dropzone.addEventListener('click', (e) => {
                if (e.target !== removeBtn && !removeBtn.contains(e.target)) {
                    input.click();
                }
            });

            // Drag events
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            // Handle dropped files
            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    handleFiles(files[0]);
                }
            });

            // Handle selected files
            input.addEventListener('change', (e) => {
                if (input.files.length) {
                    handleFiles(input.files[0]);
                }
            });

            // Remove button
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                input.value = '';
                previewImg.src = '';
                previewContainer.style.display = 'none';
            });

            function handleFiles(file) {
                if (!file.type.startsWith('image/')) {
                    alert('กรุณาเลือกเฉพาะไฟล์รูปภาพเท่านั้น');
                    return;
                }

                // Show loading preview
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = (event) => {
                    const img = new Image();
                    img.src = event.target.result;
                    img.onload = () => {
                        // Compress image
                        compressImage(img, file.name);
                    };
                };
            }

            function compressImage(img, filename) {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                // Set max dimensions (1024px)
                const MAX_WIDTH = 1024;
                const MAX_HEIGHT = 1024;
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);

                // Convert to blob and assign back to input using DataTransfer
                canvas.toBlob((blob) => {
                    // Display compressed preview
                    const reader = new FileReader();
                    reader.readAsDataURL(blob);
                    reader.onloadend = () => {
                        previewImg.src = reader.result;
                        previewContainer.style.display = 'flex';
                    };

                    // Set to file input
                    const compressedFile = new File([blob], filename, { type: 'image/jpeg' });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(compressedFile);
                    input.files = dataTransfer.files;
                    
                    console.log(`Original size: ${(img.src.length * 0.75 / 1024).toFixed(2)} KB, Compressed: ${(blob.size / 1024).toFixed(2)} KB`);
                }, 'image/jpeg', 0.82); // 82% quality Jpeg compression
            }
        };

        // Web Audio API beep sound generator
        function playBeep(success = true) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                if (success) {
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    gain.gain.setValueAtTime(0.08, ctx.currentTime);
                    osc.start();
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.15);
                    osc.stop(ctx.currentTime + 0.15);
                } else {
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    gain.gain.setValueAtTime(0.12, ctx.currentTime);
                    osc.start();
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
                    osc.stop(ctx.currentTime + 0.25);
                }
            } catch (e) {
                console.error("Audio Context Error:", e);
            }
        }

        // Global Barcode Key Listener (captures hardware scanner input)
        (function() {
            let barcodeBuffer = '';
            let lastKeyTime = Date.now();
            
            window.addEventListener('keydown', function(e) {
                // Ignore if focused on input elements (let standard typing work)
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    if (e.key === 'Enter') {
                        const val = e.target.value.trim();
                        if (val.length >= 5) {
                            setTimeout(() => {
                                let found = false;
                                if (document.querySelector(`tr[data-barcode="${val}"], tr[data-code="${val}"]`)) {
                                    found = true;
                                }
                                playBeep(found);
                            }, 100);
                        }
                    }
                    return;
                }
                
                const now = Date.now();
                // Scanners type very fast (usually < 40ms interval)
                if (now - lastKeyTime > 50) {
                    barcodeBuffer = '';
                }
                
                if (e.key === 'Enter') {
                    if (barcodeBuffer.length >= 5) {
                        e.preventDefault();
                        handleGlobalBarcodeScan(barcodeBuffer);
                        barcodeBuffer = '';
                    }
                } else if (e.key.length === 1) {
                    barcodeBuffer += e.key;
                }
                lastKeyTime = now;
            });

            function handleGlobalBarcodeScan(barcode) {
                const cleanBarcode = barcode.trim();
                console.log("Global Scanned Barcode:", cleanBarcode);
                
                // Highlight row in items/requisitions table
                let row = document.querySelector(`tr[data-barcode="${cleanBarcode}"], tr[data-code="${cleanBarcode}"]`);
                
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.style.transition = 'background 0.3s';
                    const originalBg = row.style.background;
                    row.style.background = 'rgba(99, 102, 241, 0.25)'; // indigo glow
                    setTimeout(() => {
                        row.style.background = originalBg;
                    }, 1500);
                    
                    playBeep(true);
                    
                    // Auto edit
                    const editBtn = row.querySelector('.table-action-btn.warning, button[onclick^="editReq"]');
                    if (editBtn) {
                        editBtn.click();
                    }
                } else {
                    // Try dropdown selection
                    const selectEl = document.querySelector('select[name="item_id"]');
                    if (selectEl && selectEl.tomselect) {
                        const ts = selectEl.tomselect;
                        const opts = ts.options;
                        let matchedVal = null;
                        for (let k in opts) {
                            if (opts[k].item_code === cleanBarcode || opts[k].barcode === cleanBarcode || k.includes(cleanBarcode)) {
                                matchedVal = k;
                                break;
                            }
                        }
                        if (matchedVal) {
                            ts.setValue(matchedVal);
                            playBeep(true);
                            return;
                        }
                    }
                    playBeep(false);
                }
            }
        })();
    </script>
</body>
</html>
