"use client"

import { create } from "zustand"

// Create a mock 13x13 board
const createMockBoard = () => {
  const board = []

  for (let y = 0; y < 13; y++) {
    const row = []
    for (let x = 0; x < 13; x++) {
      const cell = { x, y }

      // Add some pieces for demo
      if (y === 1 && x % 3 === 0) {
        cell.piece = {
          id: `piece-${x}-${y}`,
          playerId: "player1",
          type: "pawn",
        }
      }

      if (y === 11 && x % 3 === 0) {
        cell.piece = {
          id: `piece-${x}-${y}`,
          playerId: "player2",
          type: "pawn",
        }
      }

      if (y === 3 && x === 3) {
        cell.piece = {
          id: `piece-${x}-${y}`,
          playerId: "player3",
          type: "knight",
        }
      }

      if (y === 9 && x === 9) {
        cell.piece = {
          id: `piece-${x}-${y}`,
          playerId: "player4",
          type: "knight",
        }
      }

      row.push(cell)
    }
    board.push(row)
  }

  return board
}

// Mock players
const mockPlayers = [
  { id: "player1", handle: "Pirate#1234", trophies: 1250, league: "Gold", team: 1 },
  { id: "player2", handle: "Sailor#5678", trophies: 1350, league: "Gold", team: 2 },
  { id: "player3", handle: "Captain#9012", trophies: 1450, league: "Platinum", team: 1 },
  { id: "player4", handle: "Navigator#3456", trophies: 1550, league: "Platinum", team: 2 },
]

// Mock move log
const mockMoveLog = [
  {
    id: "move-1",
    playerId: "player1",
    playerHandle: "Pirate#1234",
    from: { x: 3, y: 1 },
    to: { x: 3, y: 3 },
    piece: "pawn",
    timestamp: Date.now() - 60000,
  },
  {
    id: "move-2",
    playerId: "player2",
    playerHandle: "Sailor#5678",
    from: { x: 9, y: 11 },
    to: { x: 9, y: 9 },
    piece: "pawn",
    timestamp: Date.now() - 30000,
  },
]

export const useMatchStore = create((set, get) => ({
  matchId: null,
  board: createMockBoard(),
  players: mockPlayers,
  currentTurn: "player1",
  timeRemaining: 30,
  isPaused: false,
  pausesRemaining: 3,
  moveLog: mockMoveLog,

  loadMatch: (matchId) => {
    // In a real app, this would fetch match data from the server
    set({
      matchId,
      board: createMockBoard(),
      players: mockPlayers,
      currentTurn: "player1",
      timeRemaining: 30,
      isPaused: false,
      pausesRemaining: 3,
      moveLog: mockMoveLog,
    })

    // Start the turn timer
    const timerInterval = setInterval(() => {
      const { timeRemaining, isPaused } = get()

      if (!isPaused) {
        if (timeRemaining > 0) {
          set({ timeRemaining: timeRemaining - 1 })
        } else {
          // Time's up, switch to next player
          const { currentTurn, players } = get()
          const currentIndex = players.findIndex((p) => p.id === currentTurn)
          const nextIndex = (currentIndex + 1) % players.length

          set({
            currentTurn: players[nextIndex].id,
            timeRemaining: 30,
          })
        }
      }
    }, 1000)

    // Clean up interval on unmount
    return () => clearInterval(timerInterval)
  },

  makeMove: (from, to) => {
    const { board, currentTurn, players, moveLog } = get()

    // Clone the board
    const newBoard = JSON.parse(JSON.stringify(board))

    // Get the piece at the from position
    const fromCell = newBoard[from.y][from.x]
    const piece = fromCell.piece

    if (!piece || piece.playerId !== currentTurn) {
      return // Invalid move
    }

    // Get the cell at the to position
    const toCell = newBoard[to.y][to.x]
    let capturedPiece = null

    // Check if there's a capture
    if (toCell.piece) {
      capturedPiece = toCell.piece
    }

    // Move the piece
    toCell.piece = piece
    fromCell.piece = undefined

    // Create a new move log entry
    const newMove = {
      id: `move-${moveLog.length + 1}`,
      playerId: currentTurn,
      playerHandle: players.find((p) => p.id === currentTurn)?.handle || "Unknown",
      from,
      to,
      piece: piece.type,
      timestamp: Date.now(),
    }

    if (capturedPiece) {
      newMove.capture = capturedPiece.type
    }

    // Switch to next player
    const currentIndex = players.findIndex((p) => p.id === currentTurn)
    const nextIndex = (currentIndex + 1) % players.length

    set({
      board: newBoard,
      currentTurn: players[nextIndex].id,
      timeRemaining: 30,
      moveLog: [...moveLog, newMove],
    })
  },

  surrender: () => {
    // In a real app, this would send a surrender request to the server
    alert("You surrendered the match")
    // Navigate back to lobby would happen in the component
  },
}))
