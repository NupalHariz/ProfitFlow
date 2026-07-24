import type { ProfitRecipientType } from './profit-recipients';
import type { TransactionStatus } from './transactions';

export type DashboardFinancialSummary = {
    totalRevenue: string;
    totalProductionCost: string;
    totalNetProfit: string;
    totalDistributedProfit: string;
    totalTransactions: number;
};

export type DashboardStatusSummary = Record<TransactionStatus, number>;

export type DashboardCustomerSummary = {
    id: number;
    name: string;
};

export type DashboardRecentTransaction = {
    id: number;
    transaction_number: string;
    transaction_date: string;
    status: TransactionStatus;
    total_revenue: string;
    total_net_profit: string;
    customer: DashboardCustomerSummary;
};

export type DashboardFailedDistribution = {
    id: number;
    transaction_number: string;
    transaction_date: string;
    failure_reason: string | null;
    updated_at: string;
    customer: DashboardCustomerSummary;
};

export type DashboardPendingDistribution = {
    id: number;
    transaction_number: string;
    completed_at: string | null;
    customer: DashboardCustomerSummary;
};

export type DashboardTopRecipient = {
    id: number;
    display_name: string;
    type: ProfitRecipientType;
    total_amount: string;
    contributing_transaction_count: number;
};

export type DashboardPageProps = {
    financialSummary: DashboardFinancialSummary;
    statusSummary: DashboardStatusSummary;
    recentTransactions: DashboardRecentTransaction[];
    recentFailedDistributions: DashboardFailedDistribution[];
    recentPendingDistributions: DashboardPendingDistribution[];
    topRecipients: DashboardTopRecipient[];
};
