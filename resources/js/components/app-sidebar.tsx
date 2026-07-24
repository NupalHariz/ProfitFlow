import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CircleDollarSign,
    FolderGit2,
    HandCoins,
    LayoutGrid,
    Package,
    PercentCircle,
    ReceiptText,
    Tags,
    UsersRound,
} from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import InvestorController from '@/actions/App/Http/Controllers/Investors/InvestorController';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategories/ProductCategoryController';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import ProfitSchemeController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeController';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Product Categories',
        href: ProductCategoryController.index(),
        icon: Tags,
    },
    {
        title: 'Products',
        href: ProductController.index(),
        icon: Package,
    },
    {
        title: 'Customers',
        href: CustomerController.index(),
        icon: UsersRound,
    },
    {
        title: 'Investors',
        href: InvestorController.index(),
        icon: HandCoins,
    },
    {
        title: 'Profit Recipients',
        href: ProfitRecipientController.index(),
        icon: CircleDollarSign,
    },
    {
        title: 'Profit Schemes',
        href: ProfitSchemeController.index(),
        icon: PercentCircle,
    },
    {
        title: 'Transactions',
        href: TransactionController.index(),
        icon: ReceiptText,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
