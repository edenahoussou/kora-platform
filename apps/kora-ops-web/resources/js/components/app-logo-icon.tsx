import type { ImgHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/android-chrome-192x192.png"
            alt="KORA EXPRESS"
            className={cn('rounded-md object-cover', className)}
            {...props}
        />
    );
}
