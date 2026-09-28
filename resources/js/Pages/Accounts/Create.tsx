import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccountForm } from '@/Components/features/accounts/AccountForm';
import { AccountFormShell } from '@/Components/features/accounts/AccountFormShell';
import { type AccountTeamOption, type PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface AccountsCreateProps extends PageProps {
    teams: AccountTeamOption[];
}

export default function AccountsCreate({ teams }: AccountsCreateProps) {
    return (
        <AuthenticatedLayout>
            <Head title="New Account" />

            <AccountFormShell
                crumb="New account"
                title="Create an Account"
                description="Accounts created here are verified and can sign in right away."
            >
                <AccountForm mode="create" teams={teams} />
            </AccountFormShell>
        </AuthenticatedLayout>
    );
}
