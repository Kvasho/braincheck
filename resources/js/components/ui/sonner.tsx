import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            richColors
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'var(--success)',
                    '--success-text': 'var(--success-foreground)',
                    '--success-border': 'var(--success)',
                    '--error-bg': 'var(--destructive)',
                    '--error-text': 'var(--destructive-foreground)',
                    '--error-border': 'var(--destructive)',
                    '--warning-bg': 'var(--warning)',
                    '--warning-text': 'var(--warning-foreground)',
                    '--warning-border': 'var(--warning)',
                    '--info-bg': 'var(--primary)',
                    '--info-text': 'var(--primary-foreground)',
                    '--info-border': 'var(--primary)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
