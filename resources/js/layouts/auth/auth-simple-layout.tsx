import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { login } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="dark flex min-h-svh flex-col items-center justify-center gap-6 bg-neutral-950 p-6 text-white md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={login()}
                            className="flex flex-col items-center gap-2 text-center font-medium"
                        >
                            <AppLogoIcon className="mb-1 size-12" />
                            <span className="text-lg font-semibold tracking-tight">
                                ProfitFlow
                            </span>
                            <span className="text-xs font-normal text-neutral-300">
                                Dynamic Profit Distribution System
                            </span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-neutral-300">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
