import { useCallback, useState } from 'react'
import { Trophy, User, LogOut, Menu, X, Swords, Settings, Home } from 'lucide-react'

import { cn } from '@/lib/utils'
import { useAuthStore } from "@/store/context/auth"

import { Link } from '@/renderer/Link'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { usePageContext } from '@/renderer/usePageContext.jsx'

export default function MainNav() {
  const { urlPathname } = usePageContext()
  const { user, isAuthenticated, logout } = useAuthStore()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  const navItems = [
    {
      name: 'Home',
      href: '/',
      icon: Home,
      activeOn: ['/'],
      requiresAuth: false,
      guestOnly: true,
    },
    {
      name: 'Play',
      href: '/play',
      icon: Swords,
      activeOn: ['/play'],
      requiresAuth: true,
    },
    {
      name: 'Rankings',
      href: '/rankings',
      icon: Trophy,
      activeOn: ['/rankings'],
      requiresAuth: false,
    },
    {
      name: 'Profile',
      href: '/profile',
      icon: User,
      activeOn: ['/profile'],
      requiresAuth: true,
    },
  ]

  const isActive = (item) => {
    return item.activeOn.some((path) => {
      if (path === '/') {
        return urlPathname === '/'
      }

      return urlPathname.startsWith(path)
    })
  }

  const onLogoutClick = useCallback(async () => {
    logout()

    setMobileMenuOpen(false)
  }, [setMobileMenuOpen])

  return (
    <header className="sticky top-0 z-40 w-full border-b border-ember/20 panel-texture">
      <div className="container flex h-16 items-center justify-between">
        {/* Logo */}
        <div className="flex items-center">
          <Link href={isAuthenticated ? '/play' : '/'} className="flex items-center space-x-2">
            <div className="bg-gradient-to-r from-ember to-burnt p-1.5 rounded-md">
              <Swords className="h-6 w-6 text-white" />
            </div>
            <span className="text-xl font-bold bg-gradient-to-r from-ember-light via-burnt to-brawl text-transparent bg-clip-text">
              Harbor Havoc
            </span>
          </Link>
        </div>

        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center space-x-1">
          {navItems
            .filter((item) => !item.requiresAuth || isAuthenticated)
            .filter((item) => !item.guestOnly || !isAuthenticated)
            .map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  'flex items-center px-4 py-2 text-sm font-medium rounded-md transition-colors',
                  isActive(item)
                    ? 'bg-ember/20 text-ember-light'
                    : 'text-gray-300 hover:bg-gray-800/70 hover:text-white',
                )}
              >
                <item.icon className="h-4 w-4 mr-2" />
                {item.name}
              </Link>
            ))}
        </nav>

        {/* Auth Buttons / User Menu (Desktop) */}
        {isAuthenticated ? (
          <div className="hidden md:flex items-center space-x-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="relative h-9 w-9 rounded-full">
                  <Avatar className="h-9 w-9 border border-ember/30">
                    <AvatarImage src={user?.avatar || ''} />
                    <AvatarFallback className="bg-gray-800">{user?.handle?.substring(0, 2) || '?'}</AvatarFallback>
                  </Avatar>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56 panel-texture border border-ember/20">
                <div className="flex items-center justify-start gap-2 p-2">
                  <div className="flex flex-col space-y-0.5 leading-none">
                    <p className="font-medium text-sm text-white">{user?.handle || 'Unknown Player'}</p>
                    <p className="text-xs text-gray-400">{user?.email || ''}</p>
                  </div>
                </div>
                <DropdownMenuSeparator className="bg-ember/20" />
                <DropdownMenuItem asChild>
                  <Link href="/profile" className="cursor-pointer flex items-center">
                    <User className="mr-2 h-4 w-4" />
                    <span>Profile</span>
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/rankings" className="cursor-pointer flex items-center">
                    <Trophy className="mr-2 h-4 w-4" />
                    <span>Rankings</span>
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/profile" className="cursor-pointer flex items-center">
                    <Settings className="mr-2 h-4 w-4" />
                    <span>Settings</span>
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator className="bg-ember/20" />
                <DropdownMenuItem
                  className="cursor-pointer text-ember hover:text-ember-light focus:text-ember-light"
                  onClick={onLogoutClick}
                >
                  <LogOut className="mr-2 h-4 w-4" />
                  <span>Log out</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        ) : (
          <div className="hidden md:flex items-center space-x-2">
            <Link href="/login">
              <Button variant="ghost" className="px-4">
                Login
              </Button>
            </Link>
            <Link href="/sign-up">
              <Button className="px-4 dota-button">
                Join now
              </Button>
            </Link>
          </div>
        )}

        {/* Mobile Menu Button */}
        <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setMobileMenuOpen(!mobileMenuOpen)}>
          {mobileMenuOpen ? <X className="h-6 w-6 text-gray-300" /> : <Menu className="h-6 w-6 text-gray-300" />}
        </Button>
      </div>

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div className="md:hidden panel-texture border-t border-ember/20">
          <div className="container py-4 space-y-4">
            <nav className="grid gap-2">
              {navItems
                .filter((item) => !item.requiresAuth || isAuthenticated)
                .map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={cn(
                      'flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors',
                      isActive(item)
                        ? 'bg-ember/20 text-ember-light'
                        : 'text-gray-300 hover:bg-gray-800/70 hover:text-white',
                    )}
                    onClick={() => setMobileMenuOpen(false)}
                  >
                    <item.icon className="h-5 w-5 mr-3" />
                    {item.name}
                  </Link>
                ))}
            </nav>

            {isAuthenticated ? (
              <>
                <div className="border-t border-ember/10 pt-4">
                  <div className="flex items-center px-4">
                    <Avatar className="h-10 w-10 border border-ember/30 mr-3">
                      <AvatarImage src={user?.avatar || ''} />
                      <AvatarFallback className="bg-gray-800">{user?.handle?.substring(0, 2) || '?'}</AvatarFallback>
                    </Avatar>
                    <div>
                      <p className="font-medium text-white">{user?.handle || 'Unknown Player'}</p>
                      <p className="text-xs text-gray-400">{user?.email || ''}</p>
                    </div>
                  </div>
                </div>

                <div className="border-t border-ember/10 pt-4">
                  <Button
                    variant="outline"
                    className="w-full border-ember/30 text-ember hover:bg-ember/20"
                    onClick={onLogoutClick}
                  >
                    <LogOut className="mr-2 h-4 w-4" />
                    Log out
                  </Button>
                </div>
              </>
            ) : (
              <div className="border-t border-ember/10 pt-4 flex flex-col space-y-2">
                <Link href="/login" onClick={() => setMobileMenuOpen(false)}>
                  <Button className="w-full">
                    Log in
                  </Button>
                </Link>
                <Link href="/sign-up" onClick={() => setMobileMenuOpen(false)}>
                  <Button className="w-full">
                    Sign up
                  </Button>
                </Link>
              </div>
            )}
          </div>
        </div>
      )}
    </header>
  )
}
