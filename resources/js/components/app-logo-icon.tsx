import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    alt = '',
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            {...props}
            src="/branding/profitflow-mark.png"
            alt={alt}
            className={cn('object-contain', className)}
        />
    );
}
