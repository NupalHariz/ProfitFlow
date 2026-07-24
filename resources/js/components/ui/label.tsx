import * as LabelPrimitive from '@radix-ui/react-label';
import * as React from 'react';

import { cn } from '@/lib/utils';

function Label({
    className,
    children,
    required = false,
    ...props
}: React.ComponentProps<typeof LabelPrimitive.Root> & {
    required?: boolean;
}) {
    return (
        <LabelPrimitive.Root
            data-slot="label"
            className={cn(
                'text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
                className,
            )}
            {...props}
        >
            {children}
            {required && (
                <>
                    <span aria-hidden="true"> *</span>
                    <span className="sr-only"> (required)</span>
                </>
            )}
        </LabelPrimitive.Root>
    );
}

export { Label };
