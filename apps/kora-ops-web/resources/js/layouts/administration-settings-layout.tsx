import AppLayout from '@/layouts/app-layout';

/**
 * Administration settings intentionally use the main OPS shell. The profile
 * settings layout is reserved for the signed-in user's own preferences.
 */
export default function AdministrationSettingsLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return <AppLayout>{children}</AppLayout>;
}
