'use client';

import { useRouter } from 'next/navigation';
import { LayoutDashboard, Radar } from 'lucide-react';
import { ShellProvider } from '@/shared/context/shell.context';
import { AdminShell } from '@/shared/template/admin-shell.component';
import { SidebarMenu, type SidebarMenuSection } from '@/shared/components/ui/sidebar-menu.component';
import { AuthGuard, useAuthContext } from '@/modules/auth';

// ── Rotas ─────────────────────────────────────────────────────────────────────

const DASHBOARD_ROUTE = '/dashboard';
const MONITORS_ROUTE = '/monitors';

// ── Estrutura de navegação ─────────────────────────────────────────────────────
// Menu único e plano da área privada. Adicione itens aqui conforme novas telas
// forem entregues em specs futuras.

const SIDEBAR_SECTIONS: SidebarMenuSection[] = [
  {
    id: 'main',
    items: [
      {
        id: 'dashboard',
        label: 'Dashboard',
        href: DASHBOARD_ROUTE,
        icon: LayoutDashboard,
        match: 'exact',
      },
      {
        id: 'monitors',
        label: 'Monitoramentos',
        href: MONITORS_ROUTE,
        icon: Radar,
        match: 'prefix',
      },
    ],
  },
];

// ──────────────────────────────────────────────────────────────────────────────

export default function PrivateGroupLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { user, logout } = useAuthContext();

  function handleLogout() {
    logout();
    router.push('/join');
  }

  return (
    <AuthGuard>
      <ShellProvider defaultOpen>
        <AdminShell
          sidebar={<SidebarMenu sections={SIDEBAR_SECTIONS} />}
          userName={user?.name}
          userEmail={user?.email}
          onLogout={handleLogout}
        >
          {children}
        </AdminShell>
      </ShellProvider>
    </AuthGuard>
  );
}
