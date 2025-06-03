import PropTypes from 'prop-types'

const Index = ({ player, isPlayerTurn }) => {
  const { user } = player

  return <div className="relative">
    <strong>{user.username}</strong> Turn order: {player.order + 1}
    {isPlayerTurn &&
      <span className="h-2 w-2 rounded-full bg-green-600 border-4 border-green-600 absolute top-2" />}
  </div>
}

Index.propTypes = {
  player: PropTypes.object.isRequired,
  isPlayerTurn: PropTypes.bool,
}

export default Index