import { useState } from "react"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Trophy, Crown, ArrowUpRight, ArrowDownRight } from "lucide-react"

// Mock data for rankings
const mockRankings = Array(100)
  .fill(null)
  .map((_, i) => ({
    id: i + 1,
    rank: i + 1,
    handle: `Player${i + 1}`,
    trophies: Math.floor(5000 - i * 35 + Math.random() * 20),
    league: i < 10 ? "Legendary" : i < 25 ? "Diamond" : i < 50 ? "Platinum" : i < 75 ? "Gold" : "Silver",
    change: Math.random() > 0.3 ? Math.floor(Math.random() * 10) + 1 : -Math.floor(Math.random() * 5) - 1,
  }))

export { Page }

function Page() {
  const [selectedWeek, setSelectedWeek] = useState("current")

  const getLeagueColor = (league) => {
    switch (league) {
      case "Legendary":
        return "text-brawl"
      case "Diamond":
        return "text-cyan-300"
      case "Platinum":
        return "text-indigo-400"
      case "Gold":
        return "text-burnt"
      case "Silver":
        return "text-slate"
      default:
        return "text-gray-400"
    }
  }

  return (
    <div className="container mx-auto p-4 max-w-5xl">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-3xl font-bold text-ember-light">Weekly Rankings</h1>
          <p className="text-gray-400 mt-1">Resets every Monday at 00:00 UTC</p>
        </div>

        <div className="flex items-center gap-4">
          <Select value={selectedWeek} onValueChange={setSelectedWeek}>
            <SelectTrigger className="w-[180px] bg-gray-800/70 border-ember/20">
              <SelectValue placeholder="Select Week" />
            </SelectTrigger>
            <SelectContent className="bg-gray-800 border-ember/20">
              <SelectItem value="current">Current Week</SelectItem>
              <SelectItem value="previous">Previous Week</SelectItem>
              <SelectItem value="2weeks">2 Weeks Ago</SelectItem>
              <SelectItem value="3weeks">3 Weeks Ago</SelectItem>
            </SelectContent>
          </Select>

          <Button variant="outline" className="border-ember/30 text-ember hover:bg-ember/20">
            My Ranking
          </Button>
        </div>
      </div>

      <Tabs defaultValue="global" className="w-full">
        <TabsList className="bg-gray-800/70 border border-ember/20 rounded-xl p-1 mb-6">
          <TabsTrigger
            value="global"
            className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Global
          </TabsTrigger>
          <TabsTrigger
            value="friends"
            className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Friends
          </TabsTrigger>
          <TabsTrigger
            value="solo"
            className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Solo 1v1
          </TabsTrigger>
          <TabsTrigger
            value="team"
            className="rounded-lg data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Team 2v2
          </TabsTrigger>
        </TabsList>

        <TabsContent value="global" className="space-y-6">
          {/* Top 3 Players */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {mockRankings.slice(1, 3).map((player, index) => (
              <Card key={index} className="panel-texture border border-ember/20 shadow-md rounded-2xl overflow-hidden">
                <div className="bg-gradient-to-r from-ember-dark/50 to-ember/30 p-4 flex justify-center">
                  <div className="relative">
                    <Avatar className="h-20 w-20 border-4 border-ember/30 glow-ember">
                      <AvatarFallback className="bg-gray-800 text-xl">{player.handle.substring(0, 2)}</AvatarFallback>
                    </Avatar>
                    <div className="absolute -bottom-3 left-1/2 transform -translate-x-1/2 bg-ember text-white text-sm font-bold rounded-full w-8 h-8 flex items-center justify-center border-2 border-ember-light">
                      {player.rank}
                    </div>
                  </div>
                </div>
                <CardContent className="p-4 text-center">
                  <h3 className="font-bold text-white mt-2">{player.handle}</h3>
                  <div className="flex items-center justify-center mt-1">
                    <Trophy className={`h-4 w-4 mr-1 ${getLeagueColor(player.league)}`} />
                    <span className={`text-sm ${getLeagueColor(player.league)}`}>{player.league}</span>
                  </div>
                  <div className="mt-2 text-lg font-bold text-ember-light">
                    {player.trophies.toLocaleString()} <span className="text-xs text-gray-400">trophies</span>
                  </div>
                  <div className="mt-1 flex items-center justify-center">
                    {player.change > 0 ? (
                      <div className="flex items-center text-green-400 text-xs">
                        <ArrowUpRight className="h-3 w-3 mr-1" />
                        <span>+{player.change} this week</span>
                      </div>
                    ) : (
                      <div className="flex items-center text-ember text-xs">
                        <ArrowDownRight className="h-3 w-3 mr-1" />
                        <span>{player.change} this week</span>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}

            {/* First Place - Larger Card */}
            <Card className="panel-texture border border-brawl/30 shadow-md rounded-2xl overflow-hidden md:col-start-2 md:col-end-3 md:row-start-1 order-first md:order-none">
              <div className="bg-gradient-to-r from-brawl-dark/50 to-brawl/30 p-6 flex justify-center">
                <div className="relative">
                  <Avatar className="h-24 w-24 border-4 border-brawl/50 glow-brawl">
                    <AvatarFallback className="bg-brawl-dark text-2xl">
                      {mockRankings[0].handle.substring(0, 2)}
                    </AvatarFallback>
                  </Avatar>
                  <div className="absolute -bottom-3 left-1/2 transform -translate-x-1/2 bg-brawl text-gray-900 text-sm font-bold rounded-full w-8 h-8 flex items-center justify-center border-2 border-brawl-dark">
                    1
                  </div>
                  <Crown className="absolute -top-2 left-1/2 transform -translate-x-1/2 h-8 w-8 text-brawl animate-float" />
                </div>
              </div>
              <CardContent className="p-4 text-center">
                <h3 className="font-bold text-white text-lg mt-2">{mockRankings[0].handle}</h3>
                <div className="flex items-center justify-center mt-1">
                  <Trophy className="h-4 w-4 mr-1 text-brawl" />
                  <span className="text-sm text-brawl">{mockRankings[0].league}</span>
                </div>
                <div className="mt-2 text-xl font-bold text-brawl">
                  {mockRankings[0].trophies.toLocaleString()} <span className="text-xs text-gray-400">trophies</span>
                </div>
                <div className="mt-1 flex items-center justify-center">
                  {mockRankings[0].change > 0 ? (
                    <div className="flex items-center text-green-400 text-xs">
                      <ArrowUpRight className="h-3 w-3 mr-1" />
                      <span>+{mockRankings[0].change} this week</span>
                    </div>
                  ) : (
                    <div className="flex items-center text-ember text-xs">
                      <ArrowDownRight className="h-3 w-3 mr-1" />
                      <span>{mockRankings[0].change} this week</span>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Rankings Table */}
          <Card className="panel-texture border border-ember/20 shadow-md rounded-2xl overflow-hidden">
            <CardHeader>
              <CardTitle>Top 100 Players</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-ember/20">
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                        Rank
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                        Player
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                        League
                      </th>
                      <th className="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                        Trophies
                      </th>
                      <th className="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                        Change
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-700">
                    {mockRankings.slice(0, 20).map((player) => (
                      <tr key={player.id} className="hover:bg-ember/10">
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="flex items-center">
                            {player.rank <= 3 ? (
                              <div
                                className={`w-6 h-6 flex items-center justify-center rounded-full mr-2 ${
                                  player.rank === 1
                                    ? "bg-brawl-dark/50 text-brawl"
                                    : player.rank === 2
                                      ? "bg-gray-600/50 text-gray-300"
                                      : "bg-burnt-dark/50 text-burnt"
                                }`}
                              >
                                {player.rank}
                              </div>
                            ) : (
                              <span className="text-gray-400 w-6 text-center mr-2">{player.rank}</span>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="flex items-center">
                            <Avatar className="h-8 w-8 mr-2 border border-ember/20">
                              <AvatarFallback className="bg-gray-800 text-xs">
                                {player.handle.substring(0, 2)}
                              </AvatarFallback>
                            </Avatar>
                            <span className="font-medium text-white">{player.handle}</span>
                          </div>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <div className="flex items-center">
                            <Trophy className={`h-4 w-4 mr-1 ${getLeagueColor(player.league)}`} />
                            <span className={`text-sm ${getLeagueColor(player.league)}`}>{player.league}</span>
                          </div>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-right font-medium text-ember-light">
                          {player.trophies.toLocaleString()}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap text-right">
                          {player.change > 0 ? (
                            <div className="flex items-center justify-end text-green-400">
                              <ArrowUpRight className="h-4 w-4 mr-1" />
                              <span>+{player.change}</span>
                            </div>
                          ) : (
                            <div className="flex items-center justify-end text-ember">
                              <ArrowDownRight className="h-4 w-4 mr-1" />
                              <span>{player.change}</span>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="friends">
          <Card className="panel-texture border border-ember/20 shadow-md rounded-2xl">
            <CardContent className="p-8 text-center">
              <Trophy className="h-12 w-12 text-gray-500 mx-auto mb-4" />
              <h3 className="text-xl font-medium text-gray-300 mb-2">No Friends Ranked Yet</h3>
              <p className="text-gray-400 max-w-md mx-auto">
                Add friends to see how you compare against them in the rankings.
              </p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="solo">
          <Card className="panel-texture border border-ember/20 shadow-md rounded-2xl">
            <CardHeader>
              <CardTitle>Solo 1v1 Rankings</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-gray-400 text-center py-4">
                Solo rankings will be available after you play more matches in this mode.
              </p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="team">
          <Card className="panel-texture border border-ember/20 shadow-md rounded-2xl">
            <CardHeader>
              <CardTitle>Team 2v2 Rankings</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-gray-400 text-center py-4">
                Team rankings will be available after you play more matches in this mode.
              </p>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
