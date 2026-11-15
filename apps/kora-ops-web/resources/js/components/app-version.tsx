/**
 * Deployed app version baked into the bundle at image build
 * (VITE_APP_VERSION, fallback VITE_SENTRY_RELEASE), shown under the sidebar.
 */
export function AppVersion() {
    const raw =
        import.meta.env.VITE_APP_VERSION ||
        import.meta.env.VITE_SENTRY_RELEASE ||
        'dev';
    const sha = raw.startsWith('staging-') ? raw.slice(8) : raw;
    const version = /^[0-9a-f]{12,}$/i.test(sha) ? sha.slice(0, 7) : sha;
    const env = import.meta.env.VITE_APP_ENV;
    const envLabel =
        env === 'production'
            ? 'Production'
            : env === 'staging'
              ? 'Staging'
              : null;

    return (
        <p
            className="mx-1 mt-2.5 text-[0.625rem] font-semibold tracking-wide text-white/35"
            title={`Version déployée : ${raw}`}
        >
            {version}
            {envLabel ? ` · ${envLabel}` : ''}
        </p>
    );
}
