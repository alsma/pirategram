import { useCallback, useEffect, useState } from 'react'

import GamePlayer from '@/components/GamePlayer'

export { Page }

function Page() {
  const [gameState, setGameState] = useState()

  useEffect(() => {
    const generate = async () => {
      const resp = await fetch('/api/game/active-game', { method: 'POST' })
      const data = await resp.json()

      setGameState(data)
    }

    generate()
  }, [])

  const onCellClick = useCallback(async e => {
    const isCell = e.target.dataset.col !== undefined && e.target.dataset.row !== undefined
    if (!isCell) {
      return
    }

    const resp = await fetch('/api/game/turn', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        gameHash: gameState.hash,
        col: e.target.dataset.col,
        row: e.target.dataset.row,
      })
    })

    const data = await resp.json()

    setGameState(data)
  }, [gameState])

  return (
    <>
      <h1 className="text-3xl font-bold underline">
        Game board
      </h1>

      {gameState &&
        <>
          <table onClick={onCellClick}>
            <tbody>
            {gameState.board.cells.map((rows, rowIdx) =>
              <tr key={`row-${rowIdx}`}>
                {rows.map((cell, colIdx) => {
                  let img
                  if (cell.revealed) {
                    const cellImgProps = {}
                    if (cell.direction) {
                      cellImgProps.style = {
                        transform: `rotate(${cell.direction * 90}deg)`
                      }
                    }

                    img = <img
                      className="cursor-pointer"
                      width="50"
                      height="50"
                      src={`/images/${cell.type}.png`}
                      alt={`${cell.type} cell`}
                      data-col={colIdx}
                      data-row={rowIdx}
                    />
                  } else {
                    img = <img
                      className="cursor-pointer"
                      width="50"
                      height="50"
                      src={`/images/fow.png`}
                      alt="fog of war cell"
                      data-col={colIdx}
                      data-row={rowIdx}
                    />
                  }

                  return <td key={`cell-${colIdx}`}>{img}</td>
                })}
              </tr>)}
            </tbody>
          </table>

          <h2 className="text-2xl">Players</h2>
          <div className="flex gap-10">
            <ul>
              {gameState.players.filter(gp => gp.teamId === 0).map(gp => <li key={gp.hash}>
                <GamePlayer player={gp} isPlayerTurn={gp.hash === gameState.currentTurnPlayer?.hash} />
              </li>)}
            </ul>
            <ul>
              {gameState.players.filter(gp => gp.teamId === 1).map(gp => <li key={gp.hash}>
                <GamePlayer player={gp} isPlayerTurn={gp.hash === gameState.currentTurnPlayer?.hash} />
              </li>)}
            </ul>
          </div>
        </>}
    </>
  )
}
