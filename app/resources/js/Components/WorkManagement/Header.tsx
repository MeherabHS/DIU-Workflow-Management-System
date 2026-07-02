import Dropdown from '@/Components/Dropdown';
import { HeaderBell, UserAvatar } from '@/Components/Dius/ui';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type NavItem = { label: string; href: string };

export default function Header({ title = 'DIUS Management Portal', roleLabel, children }: { title?: string; roleLabel?: string; children?: ReactNode }) {
    const { auth, homeUrl, navigation = [], url } = usePage<PageProps & { navigation?: NavItem[]; url?: string }>().props;
    const user = auth.user;
    const role = roleLabel || user?.roles?.[0] || 'User';
    const resolvedHomeUrl = homeUrl || route('dashboard');

    const isActive = (href: string): boolean => {
        if (!url) return false;
        const path = url.split('?')[0];
        return href === path || href === path + '/';
    };

    return (
        <>
            <aside className="border-b border-[#22395c] bg-[#0f1d32] text-white shadow-lg md:fixed md:inset-y-0 md:left-0 md:z-40 md:w-64 md:border-b-0 md:border-r">
                <div className="flex h-full flex-col">
                    <div className="flex items-center gap-3 px-4 py-4">
                        <Link href={resolvedHomeUrl} className="inline-flex items-center rounded-full bg-white/95 px-3 py-2 shadow-sm ring-1 ring-white/20 transition-colors hover:bg-white">
                            <img src="/images/logo_white.png" alt="DIU Logo" className="w-auto object-contain" style={{ height: '44px' }} />
                        </Link>
                        <div className="inline-flex rounded-full bg-[#1e3a5f] px-2.5 py-0.5 text-xs font-medium text-gray-300">{role}</div>
                    </div>

                    {user && navigation.length > 0 && (
                        <nav className="flex-1 space-y-1 px-3 pb-4 md:overflow-y-auto">
                            {navigation.map((item) => {
                                const active = isActive(item.href);
                                return (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={`block rounded-md px-3 py-2 text-sm font-semibold transition-colors ${
                                            active
                                                ? 'bg-[#2563eb] text-white shadow-sm'
                                                : 'text-gray-300 hover:bg-white/10 hover:text-white'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    )}
                </div>
            </aside>

            <header className="sticky top-0 z-30 border-b border-gray-200 bg-white text-gray-950 shadow-sm md:ml-64">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <div className="min-w-0">
                        <div className="truncate text-sm font-semibold text-gray-500">{title}</div>
                        <div className="truncate text-xs text-gray-400">{role}</div>
                    </div>
                    {user && (
                        <div className="flex items-center gap-3">
                            <HeaderBell />
                            <UserAvatar name={user.name} photoUrl={user.profile_photo_url} />
                            <div className="hidden text-left sm:block">
                                <div className="text-sm font-semibold text-gray-950">{user.name}</div>
                                <div className="text-xs text-gray-500">{role}</div>
                            </div>
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Account</button>
                                </Dropdown.Trigger>
                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                    <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    )}
                </div>
                {children}
            </header>
        </>
    );
}


