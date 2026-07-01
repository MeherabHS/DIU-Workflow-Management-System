import Dropdown from '@/Components/Dropdown';
import { HeaderBell, UserAvatar } from '@/Components/Dius/ui';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type NavItem = { label: string; href: string };

export default function Header({ title = 'DIUS Management Portal', roleLabel, children }: { title?: string; roleLabel?: string; children?: ReactNode }) {
    const { auth, navigation = [], url } = usePage<PageProps & { navigation?: NavItem[]; url?: string }>().props;
    const user = auth.user;
    const role = roleLabel || user?.roles?.[0] || 'User';

    const isActive = (href: string): boolean => {
        if (!url) return false;
        const path = url.split('?')[0];
        return href === path || href === path + '/';
    };

    return (
        <header className="sticky top-0 z-40 bg-[#0f1d32] text-white shadow-lg">
            {/* Top bar */}
            <div className="mx-auto max-w-6xl px-4 py-3 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('dashboard')} className="inline-flex items-center rounded-full bg-white/95 px-3 py-2 shadow-sm ring-1 ring-white/20 transition-colors hover:bg-white">
                            <img src="/images/logo_white.png" alt="DIU Logo" className="w-auto object-contain" style={{ height: '44px' }} />
                        </Link>
                        <span className="hidden rounded-full bg-[#1e3a5f] px-3 py-0.5 text-xs font-medium text-gray-300 sm:inline-block">Role: {role}</span>
                    </div>
                    {user && (
                        <div className="flex items-center gap-3">
                            <HeaderBell />
                            <UserAvatar name={user.name} photoUrl={user.profile_photo_url} />
                            <div className="hidden text-left sm:block">
                                <div className="text-sm font-semibold text-white">{user.name}</div>
                                <div className="text-xs text-gray-400">{role}</div>
                            </div>
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button className="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20">Account</button>
                                </Dropdown.Trigger>
                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                    <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    )}
                </div>
            </div>
            {/* Navigation bar */}
            {user && navigation.length > 0 && (
                <nav className="border-t border-white/10 bg-[#152238]">
                    <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div className="flex gap-1 overflow-x-auto py-2">
                            {navigation.map((item) => {
                                const active = isActive(item.href);
                                return (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={`whitespace-nowrap rounded-md px-4 py-2 text-sm font-semibold transition-colors ${
                                            active
                                                ? 'bg-[#2563eb] text-white shadow-sm'
                                                : 'text-gray-300 hover:bg-white/10 hover:text-white'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </nav>
            )}
            {children}
        </header>
    );
}
