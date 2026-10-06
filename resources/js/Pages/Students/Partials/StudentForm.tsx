import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import Textarea from '@/Components/Textarea';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { StudentDetails } from '@/Types/people';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * The form for adding and editing a student, including the student's
 * sign-in account. Pass `student` when editing.
 */
export default function StudentForm({
    student,
    genders,
    statuses,
}: {
    student?: StudentDetails;
    genders: Option[];
    statuses: Option[];
}) {
    const editing = student !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        student_number: student?.student_number ?? '',
        first_name: student?.first_name ?? '',
        middle_name: student?.middle_name ?? '',
        last_name: student?.last_name ?? '',
        birth_date: student?.birth_date ?? '',
        gender: student?.gender ?? '',
        address: student?.address ?? '',
        contact_number: student?.contact_number ?? '',
        status: student?.status ?? 'active',
        email: student?.email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('students.update', student.id));
        } else {
            post(route('students.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <Card title="Student details">
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="student_number"
                        label="Student number"
                        error={errors.student_number}
                        hint="Letters, numbers and hyphens, for example S-2026-0042."
                        required
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={data.student_number}
                            onChange={(e) => setData('student_number', e.target.value)}
                        />
                    </FormField>

                    <FormField id="first_name" label="First name" error={errors.first_name} required>
                        <TextInput value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                    </FormField>
                    <FormField id="middle_name" label="Middle name" error={errors.middle_name}>
                        <TextInput value={data.middle_name} onChange={(e) => setData('middle_name', e.target.value)} />
                    </FormField>
                    <FormField id="last_name" label="Last name" error={errors.last_name} required>
                        <TextInput value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                    </FormField>
                    <FormField id="birth_date" label="Date of birth" error={errors.birth_date}>
                        <TextInput
                            type="date"
                            value={data.birth_date}
                            onChange={(e) => setData('birth_date', e.target.value)}
                        />
                    </FormField>
                    <FormField id="gender" label="Gender" error={errors.gender}>
                        <SelectInput
                            value={data.gender}
                            options={genders}
                            placeholder="Not specified"
                            onChange={(e) => setData('gender', e.target.value)}
                        />
                    </FormField>
                    <FormField id="contact_number" label="Contact number" error={errors.contact_number}>
                        <TextInput
                            type="tel"
                            value={data.contact_number}
                            onChange={(e) => setData('contact_number', e.target.value)}
                        />
                    </FormField>
                    <FormField id="address" label="Address" error={errors.address} className="sm:col-span-2">
                        <Textarea rows={2} value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </FormField>
                    {!editing && (
                        <FormField id="status" label="Status" error={errors.status} required>
                            <SelectInput
                                value={data.status}
                                options={statuses}
                                onChange={(e) => setData('status', e.target.value)}
                            />
                        </FormField>
                    )}
                </div>
            </Card>

            <Card title="Sign-in account">
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="email"
                        label="Email"
                        error={errors.email}
                        hint="The student signs in with this address."
                        required
                        className="sm:col-span-2"
                    >
                        <TextInput type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </FormField>
                    <FormField
                        id="password"
                        label="Password"
                        error={errors.password}
                        hint={editing ? 'Leave empty to keep the current password.' : 'At least 8 characters.'}
                        required={!editing}
                    >
                        <TextInput
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="password_confirmation"
                        label="Confirm password"
                        error={errors.password_confirmation}
                        required={!editing}
                    >
                        <TextInput
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </FormField>
                </div>
            </Card>

            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                <PrimaryButton disabled={processing}>
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Add student'}
                </PrimaryButton>
                <Button
                    variant="secondary"
                    href={editing ? route('students.show', student.id) : route('students.index')}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
