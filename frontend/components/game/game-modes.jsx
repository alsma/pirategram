import { useState } from "react"
import { usePartyStore } from "@/store/party-store"
import { Swords, Users, Crown, Info } from "lucide-react"
import { toast } from "sonner"

const gameModes = [
  {
    id: "solo-1v1",
    title: "Solo 1v1",
    description: "Challenge another player in a strategic duel",
    icon: Swords,
    minParty: 1,
    maxParty: 2,
    color: "from-ember to-ember-dark",
  },
  {
    id: "team-2v2",
    title: "Team 2v2",
    description: "Team up with a friend against two opponents",
    icon: Users,
    minParty: 1,
    maxParty: 4,
    color: "from-burnt to-burnt-dark",
  },
  {
    id: "ffa-4",
    title: "Free-For-All",
    description: "Every player for themselves in a 4-player battle",
    icon: Crown,
    minParty: 1,
    maxParty: 4,
    color: "from-brawl to-brawl-dark",
  },
]

export default function GameModes() {
  const { party, startQueue } = usePartyStore()
  const [selectedMode, setSelectedMode] = useState(null)

  const isEligible = (mode) => {
    const partySize = party.length

    if (mode.id === "solo-1v1" && partySize === 1) return true
    if (mode.id === "team-2v2" && (partySize === 1 || partySize === 2 || partySize === 4)) return true
    if (mode.id === "ffa-4" && partySize >= 1 && partySize <= 4) return true

    return false
  }

  const handleSelectMode = (modeId) => {
    setSelectedMode(modeId)
  }

  const handleStartQueue = () => {
    if (selectedMode) {
      startQueue()
      toast.info(`Searching for a ${gameModes.find((m) => m.id === selectedMode)?.title} match...`)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold mb-2 text-ember-light">Game Modes</h2>
        <p className="text-gray-400">Select a mode to start matchmaking</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {gameModes.map((mode) => {
          const eligible = isEligible(mode)
          const isSelected = selectedMode === mode.id

          return (
            <div
              key={mode.id}
              className={`
                panel-texture cursor-pointer border transition-all duration-200 rounded-xl overflow-hidden
                ${
                  isSelected
                    ? "border-ember/50 ring-2 ring-ember/40 shadow-xl animate-pulse-glow"
                    : "border-ember/20 hover:border-ember/30"
                }
                ${!eligible ? "opacity-60 cursor-not-allowed" : ""}
              `}
              onClick={() => eligible && handleSelectMode(mode.id)}
            >
              <div className="p-4 pb-2">
                <div className="flex justify-between items-start">
                  <h3 className="text-xl font-semibold text-white">{mode.title}</h3>
                  <div className={`p-2 rounded-full bg-gradient-to-br ${mode.color}`}>
                    <mode.icon className="h-5 w-5 text-white" />
                  </div>
                </div>
                <p className="text-gray-400 text-sm mt-1">{mode.description}</p>
              </div>
              <div className="p-4 pt-2">
                {!eligible && (
                  <div className="flex items-center text-burnt text-sm mt-2">
                    <Info className="h-4 w-4 mr-1" />
                    <span>
                      {party.length > mode.maxParty
                        ? "Party too large for this mode"
                        : "Adjust party size for this mode"}
                    </span>
                  </div>
                )}

                <div className="flex justify-between items-center mt-4">
                  <div className="text-sm text-gray-400">
                    {mode.id === "solo-1v1" && "2 players"}
                    {mode.id === "team-2v2" && "2v2 teams"}
                    {mode.id === "ffa-4" && "4 players"}
                  </div>

                  {isSelected && (
                    <button className="dota-button text-sm py-1" onClick={handleStartQueue}>
                      Play
                    </button>
                  )}
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
