import Button from '@/Components/Button';
import Card from '@/Components/Card';
import FormField from '@/Components/FormField';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { Option } from '@/Types';
import { TeacherDetails } from '@/Types/people';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * The form for adding and editing a teacher, including their sign-in
 * account. Pass `teacher` when editing.
 */
export default function TeacherForm({
    teacher,
    genders,
    statuses,
}: {
    teacher?: TeacherDetails;
    genders: Option[];
    statuses: Option[];
}) {
    const editing = teacher !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        employee_number: teacher?.employee_number ?? '',
        first_name: teacher?.first_name ?? '',
        middle_name: teacher?.middle_name ?? '',
        last_name: teacher?.last_name ?? '',
        birth_date: teacher?.birth_date ?? '',
        gender: teacher?.gender ?? '',
        contact_number: teacher?.contact_number ?? '',
        specialization: teacher?.specialization ?? '',
        status: teacher?.status ?? 'active',
        email: teacher?.email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing) {
            put(route('teachers.update', teacher.id));
        } else {
            post(route('teachers.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <Card title="Teacher details">
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="employee_number"
                        label="Employee number"
                        error={errors.employee_number}
                        hint="Letters, numbers and hyphens, for example T-0107."
                        required
                    >
                        <TextInput
                            value={data.employee_number}
                            onChange={(e) => setData('employee_number', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="specialization"
                        label="Specialization"
                        error={errors.specialization}
                        hint="For example Mathematics or Early childhood."
                    >
                        <TextInput
                            value={data.specialization}
                            onChange={(e) => setData('specialization', e.target.value)}
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
                        hint="The teacher signs in with this address."
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
                    {processing ? 'Saving…' : editing ? 'Save changes' : 'Add teacher'}
                </PrimaryButton>
                <Button
                    variant="secondary"
                    href={editing ? route('teachers.show', teacher.id) : route('teachers.index')}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
