import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AccountForm } from '@/Components/features/accounts/AccountForm';
import { AccountFormShell } from '@/Components/features/accounts/AccountFormShell';
import { type AccountSummary, type AccountTeamOption, type PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface AccountsEditProps extends PageProps {
    account: AccountSummary;
    teams: AccountTeamOption[];
}

export default function AccountsEdit({ account, teams }: AccountsEditProps) {
    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${account.name}`} />

            <AccountFormShell crumb={account.name} title="Edit Account" description={account.email}>
                <AccountForm mode="edit" account={account} teams={teams} />
            </AccountFormShell>
        </AuthenticatedLayout>
    );
}
