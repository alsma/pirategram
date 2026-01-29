export const GroupStatus = {
  Idle: 'idle',
  Searching: 'searching',
  Proposed: 'proposed',
  Starting: 'starting',
  InMatch: 'inMatch',
}

export const SlotStatus = {
  Pending: 'pending',
  Accepted: 'accepted',
  Declined: 'declined',
}

export const GameMode = {
  OneOnOne: '1v1',
  TwoVsTwo: '2v2',
  FreeForAll4: 'ffa4',
}

export const CancelReason = {
  UserCancelled: 'userCancelled',
  Declined: 'declined',
  Timeout: 'timeout',
  SearchTimeout: 'searchTimeout',
}

export const PartyAction = {
  Created: 'created',
  Disbanded: 'disbanded',
  MemberJoined: 'memberJoined',
  MemberLeft: 'memberLeft',
  MemberKicked: 'memberKicked',
  LeaderChanged: 'leaderChanged',
  ModeChanged: 'modeChanged',
}
