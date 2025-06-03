import { useState } from "react"
import { motion } from "framer-motion"

export default function GameBoard({ board, onMakeMove, currentTurn }) {
  const [selectedCell, setSelectedCell] = useState(null)
  const [possibleMoves, setPossibleMoves] = useState([])

  const handleCellClick = (x, y) => {
    const cell = board[y][x]

    // If a cell is already selected
    if (selectedCell) {
      // If clicking on a possible move, make the move
      if (possibleMoves.some((move) => move.x === x && move.y === y)) {
        onMakeMove(selectedCell, { x, y })
        setSelectedCell(null)
        setPossibleMoves([])
        return
      }

      // If clicking on the same cell, deselect it
      if (selectedCell.x === x && selectedCell.y === y) {
        setSelectedCell(null)
        setPossibleMoves([])
        return
      }

      // If clicking on another piece of the same player, select that piece
      if (cell.piece && cell.piece.playerId === currentTurn) {
        setSelectedCell({ x, y })
        // Calculate possible moves (simplified for demo)
        setPossibleMoves(calculatePossibleMoves(x, y, board))
        return
      }
    }

    // If no cell is selected and clicking on a piece of the current player
    if (cell.piece && cell.piece.playerId === currentTurn) {
      setSelectedCell({ x, y })
      // Calculate possible moves (simplified for demo)
      setPossibleMoves(calculatePossibleMoves(x, y, board))
    }
  }

  // Simplified function to calculate possible moves
  const calculatePossibleMoves = (x, y, board) => {
    const moves = []

    // Simplified movement logic for demo
    // In a real game, this would depend on the piece type and game rules
    const directions = [
      { dx: -1, dy: 0 }, // left
      { dx: 1, dy: 0 }, // right
      { dx: 0, dy: -1 }, // up
      { dx: 0, dy: 1 }, // down
      { dx: -1, dy: -1 }, // diagonal up-left
      { dx: 1, dy: -1 }, // diagonal up-right
      { dx: -1, dy: 1 }, // diagonal down-left
      { dx: 1, dy: 1 }, // diagonal down-right
    ]

    directions.forEach(({ dx, dy }) => {
      const newX = x + dx
      const newY = y + dy

      // Check if the new position is within the board
      if (newX >= 0 && newX < 13 && newY >= 0 && newY < 13) {
        const targetCell = board[newY][newX]

        // Can move to empty cells or capture opponent pieces
        if (!targetCell.piece || (targetCell.piece && targetCell.piece.playerId !== currentTurn)) {
          moves.push({ x: newX, y: newY })
        }
      }
    })

    return moves
  }

  const getCellColor = (x, y) => {
    // Check if the cell is selected
    if (selectedCell && selectedCell.x === x && selectedCell.y === y) {
      return "bg-ember/40 ring-2 ring-ember/60"
    }

    // Check if the cell is a possible move
    if (possibleMoves.some((move) => move.x === x && move.y === y)) {
      return "bg-ember/20 ring-1 ring-ember/40"
    }

    // Alternating pattern for the board
    return (x + y) % 2 === 0 ? "bg-gray-900/80" : "bg-gray-800/80"
  }

  const getPieceColor = (playerId) => {
    switch (playerId) {
      case "player1":
        return "text-ember-light"
      case "player2":
        return "text-burnt"
      case "player3":
        return "text-brawl"
      case "player4":
        return "text-slate-light"
      default:
        return "text-gray-400"
    }
  }

  const getPieceIcon = (type) => {
    // This would be replaced with actual piece icons
    return "●"
  }

  return (
    <div className="w-full max-w-3xl aspect-square">
      <div className="grid grid-cols-13 grid-rows-13 h-full w-full border border-ember/30 rounded-lg overflow-hidden shadow-xl">
        {board.map((row, y) =>
          row.map((cell, x) => (
            <motion.div
              key={`${x}-${y}`}
              className={`
                relative flex items-center justify-center cursor-pointer
                ${getCellColor(x, y)}
                ${x === 0 || x === 12 || y === 0 || y === 12 ? "border border-ember/10" : ""}
              `}
              onClick={() => handleCellClick(x, y)}
              whileHover={{ scale: 0.95 }}
              transition={{ duration: 0.1 }}
            >
              {cell.piece && (
                <div className={`text-2xl font-bold ${getPieceColor(cell.piece.playerId)}`}>
                  {getPieceIcon(cell.piece.type)}
                </div>
              )}

              {/* Coordinates for debugging - would be removed in production */}
              <div className="absolute bottom-0 right-0 text-[8px] text-gray-500 opacity-50">
                {x},{y}
              </div>
            </motion.div>
          )),
        )}
      </div>
    </div>
  )
}
