import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Trophy } from "lucide-react"

import placeholderImg from '@/assets/placeholder.svg'

export default function PlayerInfo({ player, isCurrentTurn }) {
  const getTeamColor = (team) => {
    if (!team) return ""
    return team === 1 ? "border-ember/50" : "border-burnt/50"
  }

  const getLeagueColor = (league) => {
    switch (league) {
      case "Legendary":
        return "text-brawl"
      case "Diamond":
        return "text-slate-light"
      case "Platinum":
        return "text-burnt-light"
      case "Gold":
        return "text-burnt"
      case "Silver":
        return "text-slate"
      default:
        return "text-gray-400"
    }
  }

  return (
    <div
      className={`
        flex items-center p-2 rounded-lg transition-all duration-300 panel-texture
        ${isCurrentTurn ? "border border-ember/50 ring-2 ring-ember/30 animate-pulse-glow" : "border border-gray-800"}
        ${player.team ? getTeamColor(player.team) : ""}
      `}
    >
      <Avatar className="h-10 w-10 mr-3 border border-gray-700">
        <AvatarImage src={player.avatar || placeholderImg} />
        <AvatarFallback className="bg-gray-800 text-xs">{player.handle.substring(0, 2)}</AvatarFallback>
      </Avatar>

      <div className="flex-1 min-w-0">
        <div className="flex items-center">
          <div className="font-medium text-white truncate mr-2">{player.handle}</div>
          {isCurrentTurn && <div className="h-2 w-2 rounded-full bg-ember animate-pulse" />}
        </div>

        <div className="flex items-center text-xs">
          <Trophy className={`h-3 w-3 mr-1 ${getLeagueColor(player.league)}`} />
          <span className={getLeagueColor(player.league)}>{player.league}</span>
        </div>
      </div>
    </div>
  )
}
