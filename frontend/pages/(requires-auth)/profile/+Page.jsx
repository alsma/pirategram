
import { useEffect } from "react"
import { useAuthStore } from "@/store/context/auth"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Input } from "@/components/ui/input"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { LineChart, Trophy, ArrowUpRight } from "lucide-react"
import { navigate } from 'vike/client/router'

export default function ProfilePage() {
  const { user, isAuthenticated, updateProfile, logout } = useAuthStore()

  useEffect(() => {
    if (!isAuthenticated) {
      navigate("/")
    }
  }, [isAuthenticated])

  const handleUpdateProfile = (e) => {
    e.preventDefault()
    // Implementation for profile update
  }

  return (
    <div className="container mx-auto p-4 max-w-5xl">
      <div className="flex flex-col md:flex-row gap-8">
        {/* Left Column - Profile Info */}
        <div className="w-full md:w-1/3">
          <div className="panel-texture border border-ember/20 shadow-xl rounded-2xl overflow-hidden">
            <div className="bg-gradient-to-r from-ember-dark/50 to-ember/30 pb-8 pt-6">
              <div className="flex flex-col items-center">
                <Avatar className="h-24 w-24 border-4 border-ember/30 glow-ember">
                  <AvatarImage src={user?.avatar || ""} />
                  <AvatarFallback className="bg-gray-800 text-2xl">
                    {user?.handle?.substring(0, 2) || "?"}
                  </AvatarFallback>
                </Avatar>
                <h2 className="mt-4 text-2xl font-bold text-white">{user?.handle || "Unknown Player"}</h2>
                <div className="flex items-center mt-2 bg-gray-800/60 px-3 py-1 rounded-full border border-ember/20">
                  <Trophy className="h-4 w-4 text-brawl mr-2" />
                  <span className="text-sm font-medium text-gray-200">{user?.league || "Bronze"} League</span>
                </div>
              </div>
            </div>
            <div className="p-6">
              <div className="space-y-4">
                <div>
                  <h3 className="text-sm font-medium text-gray-400 mb-1">Trophy Count</h3>
                  <div className="flex items-center">
                    <span className="text-2xl font-bold text-ember-light">{user?.trophies || 0}</span>
                    <div className="ml-2 flex items-center text-green-400">
                      <ArrowUpRight className="h-4 w-4 mr-1" />
                      <span className="text-xs">+24 this week</span>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4 pt-2">
                  <div className="bg-gray-800/30 p-3 rounded-xl border border-ember/10">
                    <h3 className="text-xs font-medium text-gray-400 mb-1">Win Rate</h3>
                    <span className="text-xl font-bold text-white">68%</span>
                  </div>
                  <div className="bg-gray-800/30 p-3 rounded-xl border border-ember/10">
                    <h3 className="text-xs font-medium text-gray-400 mb-1">Matches</h3>
                    <span className="text-xl font-bold text-white">142</span>
                  </div>
                </div>

                <button className="dota-button w-full mt-4" onClick={() => navigate("/rankings")}>
                  <LineChart className="h-4 w-4 mr-2 inline-block" />
                  View Rankings
                </button>

                <button
                  className="w-full mt-2 text-gray-400 hover:text-white hover:bg-gray-700/50 font-medium px-4 py-2 rounded-md transition-all duration-200"
                  onClick={logout}
                >
                  Sign Out
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Right Column - Settings & Stats */}
        <div className="w-full md:w-2/3">
          <Tabs defaultValue="settings" className="w-full">
            <TabsList className="panel-texture border border-ember/20 rounded-xl p-1 mb-6">
              <TabsTrigger
                value="settings"
                className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
              >
                Account Settings
              </TabsTrigger>
              <TabsTrigger
                value="stats"
                className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
              >
                Game Statistics
              </TabsTrigger>
              <TabsTrigger
                value="history"
                className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
              >
                Match History
              </TabsTrigger>
            </TabsList>

            <TabsContent value="settings">
              <div className="panel-texture border border-ember/20 shadow-xl rounded-2xl">
                <div className="p-4 border-b border-ember/10">
                  <h3 className="text-xl font-semibold">Account Settings</h3>
                </div>
                <div className="p-6">
                  <form onSubmit={handleUpdateProfile} className="space-y-6">
                    <div className="space-y-2">
                      <label className="text-sm font-medium text-gray-300">Email</label>
                      <Input
                        type="email"
                        defaultValue={user?.email || ""}
                        className="bg-gray-800/50 border-gray-700 focus:border-ember focus:ring-1 focus:ring-ember/50"
                      />
                    </div>

                    <div className="space-y-2">
                      <label className="text-sm font-medium text-gray-300">Handle</label>
                      <Input
                        defaultValue={user?.handle || ""}
                        className="bg-gray-800/50 border-gray-700 focus:border-ember focus:ring-1 focus:ring-ember/50"
                      />
                    </div>

                    <div className="space-y-2">
                      <label className="text-sm font-medium text-gray-300">Password</label>
                      <Input
                        type="password"
                        placeholder="••••••••"
                        className="bg-gray-800/50 border-gray-700 focus:border-ember focus:ring-1 focus:ring-ember/50"
                      />
                    </div>

                    <div className="space-y-2">
                      <label className="text-sm font-medium text-gray-300">Avatar URL</label>
                      <Input
                        defaultValue={user?.avatar || ""}
                        className="bg-gray-800/50 border-gray-700 focus:border-ember focus:ring-1 focus:ring-ember/50"
                      />
                    </div>

                    <button type="submit" className="dota-button w-full">
                      Save Changes
                    </button>
                  </form>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="stats">
              <div className="panel-texture border border-ember/20 shadow-xl rounded-2xl">
                <div className="p-4 border-b border-ember/10">
                  <h3 className="text-xl font-semibold">Game Statistics</h3>
                </div>
                <div className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    {[
                      { label: "Total Matches", value: "142", icon: "🏅" },
                      { label: "Win Rate", value: "68%", icon: "🏆" },
                      { label: "Avg. Match Duration", value: "14m", icon: "📊" },
                    ].map((stat, index) => (
                      <div
                        key={index}
                        className="bg-gray-800/30 p-4 rounded-xl flex flex-col items-center justify-center border border-ember/10"
                      >
                        <span className="text-2xl mb-2">{stat.icon}</span>
                        <span className="text-2xl font-bold text-white">{stat.value}</span>
                        <span className="text-xs text-gray-400">{stat.label}</span>
                      </div>
                    ))}
                  </div>

                  <div className="space-y-4">
                    <h3 className="text-lg font-medium text-gray-200">Mode Performance</h3>

                    {[
                      { mode: "Solo 1v1", matches: 78, winRate: 72, color: "bg-ember" },
                      { mode: "Team 2v2", matches: 45, winRate: 64, color: "bg-burnt" },
                      { mode: "FFA", matches: 19, winRate: 58, color: "bg-brawl" },
                    ].map((mode, index) => (
                      <div key={index} className="bg-gray-800/20 p-4 rounded-xl border border-ember/10">
                        <div className="flex justify-between mb-2">
                          <span className="font-medium text-gray-200">{mode.mode}</span>
                          <span className="text-gray-400">{mode.matches} matches</span>
                        </div>
                        <div className="w-full bg-gray-700 rounded-full h-2.5">
                          <div
                            className={`${mode.color} h-2.5 rounded-full`}
                            style={{ width: `${mode.winRate}%` }}
                          ></div>
                        </div>
                        <div className="flex justify-between mt-1">
                          <span className="text-xs text-gray-400">Win Rate</span>
                          <span className="text-xs font-medium text-gray-300">{mode.winRate}%</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="history">
              <div className="panel-texture border border-ember/20 shadow-xl rounded-2xl">
                <div className="p-4 border-b border-ember/10">
                  <h3 className="text-xl font-semibold">Match History</h3>
                </div>
                <div className="p-6">
                  <div className="space-y-4">
                    {[...Array(5)].map((_, index) => (
                      <div
                        key={index}
                        className={`p-4 rounded-xl border ${
                          index % 2 === 0 ? "border-green-500/20 bg-green-900/10" : "border-ember/20 bg-ember/10"
                        }`}
                      >
                        <div className="flex justify-between items-center">
                          <div>
                            <div className="text-sm font-medium text-gray-200">
                              {index % 2 === 0 ? "Victory" : "Defeat"} - Solo 1v1
                            </div>
                            <div className="text-xs text-gray-400 mt-1">
                              {new Date(Date.now() - index * 86400000).toLocaleDateString()}
                            </div>
                          </div>
                          <div className="text-right">
                            <div className={`text-sm font-medium ${index % 2 === 0 ? "text-green-400" : "text-ember"}`}>
                              {index % 2 === 0 ? "+24" : "-12"} trophies
                            </div>
                            <div className="text-xs text-gray-400 mt-1">12m 34s</div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </div>
  )
}
