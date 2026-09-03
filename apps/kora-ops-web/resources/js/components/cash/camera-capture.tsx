import { AlertTriangle, Camera, Check, RefreshCw, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export type CameraCaptureValue = {
    file: File;
    capturedAt: string;
    previewUrl: string;
};

type CameraStatus =
    'idle' | 'starting' | 'live' | 'denied' | 'unsupported' | 'error';

type CameraCaptureProps = {
    value: CameraCaptureValue | null;
    onChange: (value: CameraCaptureValue | null) => void;
    label: string;
    error?: string;
    disabled?: boolean;
};

const statusMessages: Record<Exclude<CameraStatus, 'idle' | 'live'>, string> = {
    starting: 'Démarrage de la caméra…',
    denied: 'Autorisation caméra refusée. Activez l’accès à la caméra dans les paramètres de votre navigateur, puis réessayez.',
    unsupported:
        'Aucune caméra détectée, ou votre navigateur ne prend pas en charge la capture en direct.',
    error: 'Échec de la capture. Réessayez.',
};

/**
 * Live camera capture widget. Deliberately does NOT expose a
 * `<input type="file">` fallback: the business rule is that opening/closing
 * cash evidence can only come from a photo taken live, through this
 * getUserMedia flow, never from an existing file on disk.
 */
export function CameraCapture({
    value,
    onChange,
    label,
    error,
    disabled,
}: CameraCaptureProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [status, setStatus] = useState<CameraStatus>('idle');
    const [stream, setStream] = useState<MediaStream | null>(null);

    const stopStream = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setStream(null);
    };

    // Always release the camera when the widget unmounts (dialog closed,
    // navigation, etc.) so the device indicator light turns off.
    useEffect(() => stopStream, []);

    // The <video> element only exists in the DOM once `status` is 'live'
    // (see the JSX below), so the stream can only be safely attached to
    // `videoRef` in an effect that runs after that render has committed —
    // not synchronously inside `start()`, where the ref would still be
    // null. This is what was causing "Échec de la capture": the preview
    // appeared to work, but no stream was actually attached, so the video
    // never had any real dimensions to capture from.
    useEffect(() => {
        if (status !== 'live' || !stream || !videoRef.current) {
            return;
        }

        const video = videoRef.current;
        video.srcObject = stream;
        video.play().catch(() => setStatus('error'));

        return () => {
            video.srcObject = null;
        };
    }, [status, stream]);

    const start = async () => {
        if (disabled) {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus('unsupported');

            return;
        }

        setStatus('starting');

        try {
            const mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            streamRef.current = mediaStream;
            setStream(mediaStream);
            setStatus('live');
        } catch (err) {
            const name = err instanceof DOMException ? err.name : '';

            if (name === 'NotAllowedError' || name === 'SecurityError') {
                setStatus('denied');
            } else if (
                name === 'NotFoundError' ||
                name === 'OverconstrainedError'
            ) {
                setStatus('unsupported');
            } else {
                setStatus('error');
            }
        }
    };

    const cancel = () => {
        stopStream();
        setStatus('idle');
    };

    const capture = () => {
        const video = videoRef.current;

        if (!video) {
            setStatus('error');

            return;
        }

        const takeSnapshot = () => {
            if (video.videoWidth === 0 || video.videoHeight === 0) {
                setStatus('error');

                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');

            if (!context) {
                setStatus('error');

                return;
            }

            context.drawImage(video, 0, 0);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        setStatus('error');

                        return;
                    }

                    const capturedAt = new Date().toISOString();
                    const file = new File(
                        [blob],
                        `preuve-caisse-${Date.now()}.jpg`,
                        { type: 'image/jpeg' },
                    );
                    stopStream();
                    setStatus('idle');
                    onChange({
                        file,
                        capturedAt,
                        previewUrl: URL.createObjectURL(blob),
                    });
                },
                'image/jpeg',
                0.85,
            );
        };

        // HAVE_CURRENT_DATA (2) or above means at least one frame is
        // decoded and ready to draw. On some devices `play()` can resolve
        // a beat before that, so give it one more chance via the
        // `loadeddata` event rather than failing immediately.
        if (video.readyState >= 2) {
            takeSnapshot();
        } else {
            const onReady = () => {
                video.removeEventListener('loadeddata', onReady);
                window.clearTimeout(timeoutId);
                takeSnapshot();
            };
            video.addEventListener('loadeddata', onReady);
            const timeoutId = window.setTimeout(() => {
                video.removeEventListener('loadeddata', onReady);
                takeSnapshot();
            }, 1500);
        }
    };

    const retake = () => {
        if (value) {
            URL.revokeObjectURL(value.previewUrl);
        }

        onChange(null);
        void start();
    };

    return (
        <div className="camera-capture">
            <span className="camera-capture-label">
                <Camera className="size-3.5" />
                {label} <b>*</b>
            </span>

            {status === 'live' && (
                <div className="camera-capture-live">
                    <video
                        ref={videoRef}
                        className="camera-capture-video"
                        muted
                        playsInline
                    />
                    <div className="camera-capture-live-actions">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={cancel}
                        >
                            <X className="size-3.5" /> Annuler
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={capture}
                        >
                            <Camera className="size-3.5" /> Capturer
                        </button>
                    </div>
                </div>
            )}

            {status !== 'live' && value && (
                <div className="camera-capture-preview">
                    <img
                        src={value.previewUrl}
                        alt="Aperçu de la preuve capturée"
                    />
                    <div className="camera-capture-preview-info">
                        <span className="cash-evidence-ready">
                            <Check className="size-3.5" /> Preuve capturée
                        </span>
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={retake}
                            disabled={disabled}
                        >
                            <RefreshCw className="size-3.5" /> Reprendre
                        </button>
                    </div>
                </div>
            )}

            {status !== 'live' && !value && (
                <div className="camera-capture-idle">
                    <button
                        type="button"
                        className="btn btn-ghost camera-capture-trigger"
                        onClick={start}
                        disabled={disabled || status === 'starting'}
                    >
                        <Camera className="size-3.5" />
                        {status === 'starting'
                            ? 'Démarrage…'
                            : 'Ouvrir la caméra'}
                    </button>
                    {status !== 'idle' && status !== 'starting' && (
                        <small className="camera-capture-status">
                            <AlertTriangle className="size-3" />{' '}
                            {statusMessages[status]}
                        </small>
                    )}
                </div>
            )}

            {error && <small className="field-error-text">{error}</small>}
        </div>
    );
}
