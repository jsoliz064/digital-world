window.CameraHandler = (function () {
    const streams = {};
    const readyStates = {};
    const lastTaps = {};

    function getElements(modalId) {
        return {
            modal: document.getElementById(modalId),
            video: document.getElementById(`${modalId}-video`),
            canvas: document.getElementById(`${modalId}-canvas`),
        };
    }

    async function initCamera(modalId) {
        const { modal, video } = getElements(modalId);

        if (!modal || !video) {
            console.error(`Camera elements not found for ${modalId}`);
            return;
        }

        closeCamera(modalId);
        modal.classList.remove("hidden");
        video.srcObject = null;

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: "environment",
                },
                audio: false,
            });

            streams[modalId] = stream;
            video.srcObject = stream;
            video.muted = true;
            video.playsInline = true;

            await new Promise((resolve) => {
                video.onplaying = () => {
                    readyStates[modalId] = true;
                    resolve();
                };
            });
        } catch (err) {
            console.error("Camera error:", err);
            alert(
                `Camera error: ${
                    err.name === "NotAllowedError"
                        ? "Please allow camera access"
                        : err.name === "NotFoundError"
                        ? "No camera found"
                        : err.name === "NotReadableError"
                        ? "Camera in use by another app"
                        : "Failed to access camera"
                }`
            );
            closeCamera(modalId);
        }
    }

    function closeCamera(modalId) {
        if (streams[modalId]) {
            streams[modalId].getTracks().forEach((track) => track.stop());
            delete streams[modalId];
        }

        const { modal, video } = getElements(modalId);
        if (video) {
            video.pause();
            video.srcObject = null;
        }
        if (modal) {
            modal.classList.add("hidden");
        }

        delete readyStates[modalId];
        delete lastTaps[modalId];
    }

    function handleDoubleTap(event, modalId, callback) {
        const now = Date.now();
        const doubleTapDelay = 300; 

        if (lastTaps[modalId]?.timeout) {
            clearTimeout(lastTaps[modalId].timeout);
        }

        if (
            lastTaps[modalId] &&
            now - lastTaps[modalId].time < doubleTapDelay
        ) {
            event.preventDefault();
            const photo = capturePhoto(modalId);
            if (photo && callback) callback(photo);
            delete lastTaps[modalId];
        } else {
            lastTaps[modalId] = {
                time: now,
                timeout: setTimeout(
                    () => delete lastTaps[modalId],
                    doubleTapDelay
                ),
            };
        }
    }

    function capturePhoto(modalId) {
        const { video, canvas } = getElements(modalId);

        if (!readyStates[modalId] || !video.videoWidth) {
            alert("Please wait for camera to be ready");
            return null;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(video, 0, 0);

        video.style.filter = "brightness(2)";
        setTimeout(() => (video.style.filter = ""), 200);
        closeCamera(modalId);
        return canvas.toDataURL("image/jpeg");
    }

    return {
        initCamera,
        closeCamera,
        handleDoubleTap,
        capturePhoto,
    };
})();
