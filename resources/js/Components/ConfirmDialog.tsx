import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import { DialogTitle } from '@headlessui/react';
import { ReactNode } from 'react';

interface Props {
    show: boolean;
    title: string;
    message?: ReactNode;
    confirmLabel?: string;
    /** Use "primary" for actions that are not destructive. */
    tone?: 'danger' | 'primary';
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
}

/** Asks the user to confirm an action before it runs. */
export default function ConfirmDialog({
    show,
    title,
    message,
    confirmLabel = 'Confirm',
    tone = 'danger',
    processing = false,
    onConfirm,
    onClose,
}: Props) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <DialogTitle className="font-display text-lg font-semibold">
                    {title}
                </DialogTitle>
                {message && <div className="mt-2 text-muted">{message}</div>}

                <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant={tone} onClick={onConfirm} disabled={processing}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
