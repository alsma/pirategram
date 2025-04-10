import { useCallback, useEffect, useMemo, useState } from 'react'

import GamePlayer from '@/components/GamePlayer'

export { Page }

function getPositionKey(col, row) {
  return `${col}-${row}`
}

const EntityType = {
  Pirate: 'pirate',
  Coin: 'coin',
}

const PirateClassesByTeamId = {
  0: 'bg-green-600 border-green-600',
  1: 'bg-red-600 border-red-600',
  2: 'bg-blue-600 border-blue-600',
  3: 'bg-white-600 border-white-600',
}

const PiratePositionByCnt = {
  0: 'top-2 left-2',
  1: 'top-2 left-6',
  2: 'top-6 left-4',
}
const CoinPositionByCnt = {
  0: 'bottom-2 right -2',
  1: 'bottom-4 right-6',
  2: 'bottom-6 right-4',
}

function Page() {
  const [gameState, setGameState] = useState()
  const [selectedEntity, setSelectedEntity] = useState()
  const [selectedCarriageEntity, setSelectedCarriageEntity] = useState()

  const myPlayer = useMemo(() => {
    if (!gameState) {
      return null
    }

    return gameState.currentTurnPlayer
  }, [gameState])

  const entitiesByPosition = useMemo(() => {
    if (!gameState?.entities) {
      return {}
    }

    return gameState.entities.reduce((acc, entity) => {
      const position = getPositionKey(entity.col, entity.row)

      acc[position] = acc[position] || []
      acc[position].push(entity)

      return acc
    }, {})
  }, [gameState])

  const teamByPlayerHash = useMemo(() => {
    if (!gameState?.players) {
      return {}
    }

    return gameState.players.reduce((acc, player) => {
      acc[player.hash] = player.teamId

      return acc
    }, {})
  }, [gameState])

  const allowedTurnsByEntityId = useMemo(() => {
    if (!gameState?.allowedTurns) {
      return {}
    }

    return gameState.allowedTurns.reduce((acc, turn) => {
      const position = getPositionKey(turn.col, turn.row)

      acc[turn.entityId] = acc[turn.entityId] || {}
      acc[turn.entityId][position] = turn

      return acc
    }, {})
  }, [gameState])

  useEffect(() => {
    if (!allowedTurnsByEntityId) {
      return
    }

    const allowedTurnEntityIds = Object.keys(allowedTurnsByEntityId)
    if (allowedTurnEntityIds.length === 1) {
      setSelectedEntity(gameState.entities.find(e => !!allowedTurnsByEntityId[e.id]))
    }
  }, [allowedTurnsByEntityId]);

  useEffect(() => {
    const generate = async () => {
      const resp = await fetch('/api/game/active-game', { method: 'POST' })
      const data = await resp.json()

      setGameState(data)
    }

    generate()
  }, [])

  const handleCellClick = async (selectedEntity, col, row) => {
    if (!selectedEntity) {
      return
    }

    const isAllowedTurn = allowedTurnsByEntityId[selectedEntity.id] && allowedTurnsByEntityId[selectedEntity.id][getPositionKey(col, row)]
    if (!isAllowedTurn) {
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
        entityId: selectedEntity.id,
        carriageEntityId: selectedCarriageEntity?.id,
        col,
        row,
      })
    })

    const data = await resp.json()

    setGameState(data)
    setSelectedEntity(null)

    if (data.currentTurnPlayer.hash !== selectedEntity.playerHash) {
      setSelectedCarriageEntity(null)
    }
  }

  const handleEntityClick = async (entityId) => {
    const entity = gameState?.entities?.find(e => e.id === entityId)
    if (!entity) {
      return
    }

    if (entity.playerHash !== myPlayer?.hash) {
      if (selectedEntity && entity.type === EntityType.Coin && entity.col === selectedEntity.col && entity.row === selectedEntity.row) {
        setSelectedCarriageEntity(setSelectedCarriageEntity?.id === entity.id ? null : entity)
      }
      return
    }

    if (!allowedTurnsByEntityId[entity.id]) {
      return
    }

    if (selectedEntity?.id === entity.id) {
      setSelectedEntity(null)

      return
    }

    setSelectedEntity(entity)
  }

  const onCellClick = useCallback(async e => {
    const entityId = e.target.dataset.entityId
    const hasActiveTurn = e.target.dataset.activeTurn
    if (entityId) {
      if (hasActiveTurn && selectedEntity) {
        return handleCellClick(selectedEntity, e.target.dataset.col, e.target.dataset.row)
      }

      return handleEntityClick(entityId)
    }

    const isCell = e.target.dataset.col !== undefined && e.target.dataset.row !== undefined
    if (isCell) {
      return handleCellClick(selectedEntity, e.target.dataset.col, e.target.dataset.row)
    }
  }, [gameState, selectedEntity, selectedCarriageEntity])

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
                  const hasActiveTurn = selectedEntity &&
                    allowedTurnsByEntityId[selectedEntity.id] &&
                    allowedTurnsByEntityId[selectedEntity.id][getPositionKey(colIdx, rowIdx)]
                  const activeTurn = hasActiveTurn ? 'animate-pulse cursor-pointer' : ''

                  let img
                  if (cell.revealed) {
                    const cellImgProps = {}
                    if (cell.direction) {
                      cellImgProps.style = {
                        transform: `rotate(${cell.direction * 90}deg)`
                      }
                    }

                    img = <img
                      key="cell"
                      className={activeTurn}
                      width="50"
                      height="50"
                      src={`/images/${cell.type}.png`}
                      alt={`${cell.type} cell`}
                      data-col={colIdx}
                      data-row={rowIdx}
                      {...cellImgProps}
                    />
                  } else {
                    img = <img
                      key="cell"
                      className={activeTurn}
                      width="50"
                      height="50"
                      src={`/images/fow.png`}
                      alt="fog of war cell"
                      data-col={colIdx}
                      data-row={rowIdx}
                    />
                  }

                  const children = [img]
                  const entities = entitiesByPosition[getPositionKey(colIdx, rowIdx)] || []

                  let pirateCnt = 0
                  let coinCnt = 0
                  entities.forEach((e, i) => {
                    if (e.isKilled === true) {
                      return
                    }

                    const isMyEntity = myPlayer?.hash === e.playerHash
                    const hasTurns = allowedTurnsByEntityId[e.id]
                    const availableForSelection = isMyEntity && hasTurns ? 'outline-1 outline-offset-1 outline-dotted outline-yellow-300' : ''
                    const selected = selectedEntity?.id === e.id ? 'animate-bounce' : ''

                    if (e.type === EntityType.Pirate) {
                      const color = PirateClassesByTeamId[teamByPlayerHash[e.playerHash]]
                      const position = PiratePositionByCnt[pirateCnt]

                      children.push(
                        <span
                          key={`entity:${e.id}`}
                          className={`cursor-pointer h-3 w-3 rounded-full border-4 absolute z-20 ${position} ${color} ${availableForSelection} ${selected}`}
                          data-entity-id={e.id}
                        />
                      )

                      pirateCnt++

                      return
                    } else if (e.type === EntityType.Coin) {
                      const position = CoinPositionByCnt[coinCnt]
                      const selectedCarriage = selectedCarriageEntity?.id === e.id ? 'animate-bounce' : ''

                      children.push(
                        <span
                          key={`entity:${e.id}`}
                          className={`cursor-pointer h-3 w-3 rounded-full border-4 absolute z-20 bg-amber-600 border-amber-600 ${position} ${selectedCarriage}`}
                          data-entity-id={e.id}
                        />
                      )

                      coinCnt++

                      return
                    }

                    children.push(<img
                      key={`entity:${e.id}`}
                      className={`absolute cursor-pointer z-10 top-0 left-0 ${availableForSelection} ${selected} ${activeTurn}`}
                      width="50"
                      height="50"
                      src={`/images/${e.type}.png`}
                      alt={`${e.type}`}
                      data-entity-id={e.id}
                      data-col={e.col}
                      data-row={e.row}
                      data-active-turn={!!hasTurns}
                    />)
                  })

                  return <td key={`cell-${colIdx}`} className="relative">{children}</td>
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
