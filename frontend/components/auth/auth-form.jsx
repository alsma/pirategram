import { useState } from 'react'
import { Mail, Lock, User } from 'lucide-react'
import { navigate } from 'vike/client/router'
import { toast } from 'sonner'

import { useAuthStore } from '@/store/context/auth'

import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

export default function AuthForm({ defaultPanel }) {
  const { login, signup } = useAuthStore()
  const [isLoading, setIsLoading] = useState(false)

  const handleLogin = async (e) => {
    e.preventDefault()
    setIsLoading(true)

    const form = e.target
    const formData = new FormData(form)
    const identifier = formData.get("identifier")
    const password = formData.get("password")

    try {
      await login(identifier, password)
      navigate("/play")
      toast.success("Login successful!")
    } catch (error) {
      toast.error("Login failed. Please check your credentials.")
    } finally {
      setIsLoading(false)
    }
  }

  const handleSignup = async (e) => {
    e.preventDefault()
    setIsLoading(true)

    const form = e.target
    const formData = new FormData(form)
    const email = formData.get("email")

    try {
      await signup(email)
      navigate("/play")
      toast.success("Account created! Check your email for verification.")
    } catch (error) {
      toast.error("Signup failed. Please try again.")
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Tabs defaultValue={defaultPanel} className="w-full">
      <TabsList className="grid w-full grid-cols-2 bg-gray-800/70 rounded-lg p-1">
        <TabsTrigger
          value="login"
          className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
        >
          Login
        </TabsTrigger>
        <TabsTrigger
          value="sign-up"
          className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
        >
          Sign Up
        </TabsTrigger>
      </TabsList>

      <TabsContent value="login" className="mt-6">
        <form onSubmit={handleLogin} className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-300">Handle or Email</label>
            <div className="relative">
              <User className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                name="identifier"
                placeholder="Pirate#1234 or email"
                className="pl-10 bg-gray-700/50 border-gray-600 focus:border-ember focus:ring-1 focus:ring-ember/50"
                required
              />
            </div>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-300">Password</label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                name="password"
                type="password"
                placeholder="••••••••"
                className="pl-10 bg-gray-700/50 border-gray-600 focus:border-ember focus:ring-1 focus:ring-ember/50"
                required
              />
            </div>
          </div>

          <button type="submit" className="dota-button w-full" disabled={isLoading}>
            {isLoading ? "Logging in..." : "Login"}
          </button>

          <div className="text-center mt-4">
            <a href="#" className="text-sm text-burnt hover:text-burnt-light">
              Forgot password?
            </a>
          </div>
        </form>
      </TabsContent>

      <TabsContent value="sign-up" className="mt-6">
        <form onSubmit={handleSignup} className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-300">Email</label>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                name="email"
                type="email"
                placeholder="your@email.com"
                className="pl-10 bg-gray-700/50 border-gray-600 focus:border-ember focus:ring-1 focus:ring-ember/50"
                required
              />
            </div>
          </div>

          <div className="bg-gray-800/50 border border-ember/20 rounded-lg p-4 text-sm text-gray-300">
            <p>After signing up:</p>
            <ul className="list-disc list-inside mt-2 space-y-1 text-gray-400">
              <li>You'll get an auto-generated handle (e.g., Pirate#1234)</li>
              <li>You can set your password and avatar later</li>
              <li>You can start playing immediately</li>
            </ul>
          </div>

          <button type="submit" className="dota-button w-full" disabled={isLoading}>
            {isLoading ? "Creating Account..." : "Create Account"}
          </button>
        </form>
      </TabsContent>
    </Tabs>
  )
}
