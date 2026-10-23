import { useHttp } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { confirmation } from '@/routes/password';
import { store as confirmPassword } from '@/routes/password/confirm';

/**
 * Confirmation before an action.
 *
 * - `confirmText`: type-to-confirm for dangerous actions; the button unlocks only
 *   when the user types exactly this text (e.g. the target's email).
 * - `requirePassword`: before running the action, check Fortify's password
 *   confirmation status and, if it has expired, ask for the password in the
 *   dialog. The route's `password.confirm` middleware is the real gate; this
 *   only spares the user a redirect.
 * - `children`: extra fields (e.g. a reason), rendered above the buttons.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    destructive = false,
    confirmText,
    requirePassword = false,
    processing = false,
    canConfirm = true,
    onConfirm,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Already translated texts. */
    title: string;
    description?: ReactNode;
    confirmLabel: string;
    destructive?: boolean;
    confirmText?: string;
    requirePassword?: boolean;
    processing?: boolean;
    /** Extra condition from the caller's own fields. */
    canConfirm?: boolean;
    onConfirm: () => void;
    children?: ReactNode;
}) {
    const { t } = useTranslation();
    const typedId = useId();
    const passwordId = useId();
    const [typed, setTyped] = useState('');
    const [step, setStep] = useState<'confirm' | 'password'>('confirm');
    const [checking, setChecking] = useState(false);
    const status = useHttp<Record<string, never>, { confirmed: boolean }>();
    const password = useHttp({ password: '' });
    const [wasOpen, setWasOpen] = useState(open);

    // Every opening starts fresh (no typed text or password left from last time).
    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setTyped('');
            setStep('confirm');
        }
    }

    const close = () => {
        password.resetAndClearErrors();
        onOpenChange(false);
    };

    const typedMatches = confirmText === undefined || typed === confirmText;
    const busy = processing || checking || password.processing;

    const proceed = async () => {
        if (!requirePassword) {
            onConfirm();

            return;
        }

        setChecking(true);

        try {
            const { confirmed } = await status.get(confirmation.url());

            if (confirmed) {
                onConfirm();
            } else {
                setStep('password');
            }
        } catch {
            setStep('password');
        } finally {
            setChecking(false);
        }
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (step === 'password') {
            try {
                await password.post(confirmPassword.url());
                password.reset();
                setStep('confirm');
                onConfirm();
            } catch {
                // Validation errors are shown below the field.
            }

            return;
        }

        if (typedMatches && canConfirm) {
            await proceed();
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? onOpenChange(true) : close())}
        >
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {step === 'password'
                                ? t('Confirm your password')
                                : title}
                        </DialogTitle>
                        <DialogDescription>
                            {step === 'password'
                                ? t(
                                      'For your security, please confirm your password to continue.',
                                  )
                                : description}
                        </DialogDescription>
                    </DialogHeader>

                    {step === 'confirm' && (
                        <>
                            {children}

                            {confirmText !== undefined && (
                                <div className="grid gap-2">
                                    <Label htmlFor={typedId}>
                                        {t('Type :text to confirm', {
                                            text: confirmText,
                                        })}
                                    </Label>
                                    <Input
                                        id={typedId}
                                        value={typed}
                                        autoComplete="off"
                                        spellCheck={false}
                                        onChange={(event) =>
                                            setTyped(event.target.value)
                                        }
                                    />
                                </div>
                            )}
                        </>
                    )}

                    {step === 'password' && (
                        <div className="grid gap-2">
                            <Label htmlFor={passwordId}>{t('Password')}</Label>
                            <PasswordInput
                                id={passwordId}
                                autoFocus
                                autoComplete="current-password"
                                value={password.data.password}
                                onChange={(event) =>
                                    password.setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={password.errors.password} />
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={close}>
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={
                                busy ||
                                (step === 'confirm' &&
                                    (!typedMatches || !canConfirm))
                            }
                        >
                            {busy && <Spinner />}
                            {step === 'password'
                                ? t('Confirm password')
                                : confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
