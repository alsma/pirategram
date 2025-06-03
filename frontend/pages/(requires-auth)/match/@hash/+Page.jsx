import { useAuthStore } from "@/store/context/auth"
import { useMatchStore } from "@/store/match-store"
import GameBoard from "@/components/game/game-board"
import PlayerInfo from "@/components/game/player-info"
import TurnTimer from "@/components/game/turn-timer"
import MoveLog from "@/components/game/move-log"
import { Button } from "@/components/ui/button"
import { Drawer } from "@/components/ui/drawer"
import { Flag, PauseCircle, ClipboardList } from "lucide-react"
import { navigate } from 'vike/client/router'
import { usePageContext } from '@/renderer/usePageContext.jsx'
import { useEffect, useState } from 'react'

export { Page }
function Page() {
  const { routeParams } = usePageContext()

  const { isAuthenticated } = useAuthStore()
  const {
    board,
    players,
    currentTurn,
    timeRemaining,
    isPaused,
    pausesRemaining,
    moveLog,
    loadMatch,
    makeMove,
    surrender,
  } = useMatchStore()

  const [drawerOpen, setDrawerOpen] = useState(false)

  useEffect(() => {
    if (!isAuthenticated) {
      navigate("/")
      return
    }

    if (routeParams.hash) {
      loadMatch(routeParams.hash)
    }
  }, [isAuthenticated, routeParams.hash, loadMatch])

  return (
    <div className="flex flex-col h-screen overflow-hidden">
      {/* Top Bar */}
      <div className="panel-texture border-b border-ember/20 p-4 flex justify-between items-center">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate("/play")}
          className="text-gray-300 hover:text-white"
        >
          Exit to Lobby
        </Button>

        <TurnTimer timeRemaining={timeRemaining} isPaused={isPaused} />

        <Button
          variant="ghost"
          size="sm"
          onClick={() => setDrawerOpen(true)}
          className="text-gray-300 hover:text-white md:hidden"
        >
          <ClipboardList className="h-5 w-5" />
        </Button>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* Main Game Area */}
        <div className="flex-1 flex flex-col items-center justify-center p-4 overflow-hidden">
          {/* Player Info - Top */}
          <div className="w-full max-w-3xl grid grid-cols-2 gap-4 mb-4">
            {players.slice(0, 2).map((player, index) => (
              <PlayerInfo key={index} player={player} isCurrentTurn={currentTurn === player.id} />
            ))}
          </div>

          {/* Game Board */}
          <div className="flex-1 flex items-center justify-center w-full max-w-3xl">
            <GameBoard board={board} onMakeMove={makeMove} currentTurn={currentTurn} />
          </div>

          {/* Player Info - Bottom */}
          <div className="w-full max-w-3xl grid grid-cols-2 gap-4 mt-4">
            {players.slice(2, 4).map((player, index) => (
              <PlayerInfo key={index} player={player} isCurrentTurn={currentTurn === player.id} />
            ))}
          </div>
        </div>

        {/* Right Sidebar - Hidden on Mobile */}
        <div className="hidden md:block w-80 border-l border-ember/20 panel-texture p-4 overflow-y-auto">
          <div className="flex flex-col h-full">
            <h3 className="text-xl font-semibold mb-4 text-ember-light">Move Log</h3>
            <MoveLog moves={moveLog} />

            <div className="mt-auto pt-4 space-y-3">
              <div className="flex items-center justify-between text-gray-300">
                <span>Pauses Remaining:</span>
                <div className="flex">
                  {[...Array(3)].map((_, i) => (
                    <PauseCircle
                      key={i}
                      className={`h-5 w-5 ${i < pausesRemaining ? "text-ember" : "text-gray-600"}`}
                    />
                  ))}
                </div>
              </div>

              <button
                className="w-full bg-ember/50 hover:bg-ember border border-ember/50 shadow-md text-white font-medium px-4 py-2 rounded-md transition-all duration-200"
                onClick={surrender}
              >
                <Flag className="mr-2 h-4 w-4 inline-block" /> Surrender
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Mobile Drawer */}
      <Drawer open={drawerOpen} onOpenChange={setDrawerOpen}>
        <div className="p-4 max-h-[70vh] overflow-y-auto panel-texture">
          <h3 className="text-xl font-semibold mb-4 text-ember-light">Move Log</h3>
          <MoveLog moves={moveLog} />

          <div className="mt-6 space-y-3">
            <div className="flex items-center justify-between text-gray-300">
              <span>Pauses Remaining:</span>
              <div className="flex">
                {[...Array(3)].map((_, i) => (
                  <PauseCircle key={i} className={`h-5 w-5 ${i < pausesRemaining ? "text-ember" : "text-gray-600"}`} />
                ))}
              </div>
            </div>

            <button
              className="w-full bg-ember/50 hover:bg-ember border border-ember/50 shadow-md text-white font-medium px-4 py-2 rounded-md transition-all duration-200"
              onClick={surrender}
            >
              <Flag className="mr-2 h-4 w-4 inline-block" /> Surrender
            </button>
          </div>
        </div>
      </Drawer>
    </div>
  )
}
