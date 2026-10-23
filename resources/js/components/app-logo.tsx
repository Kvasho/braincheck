import { LogoMark } from '@/components/logo';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-accent">
                <LogoMark className="size-6" aria-hidden="true" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    Brain<span className="text-primary">Check</span>
                </span>
            </div>
        </>
    );
}
