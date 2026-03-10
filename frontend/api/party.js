import { sendRequest } from '@/api/index.js'

export async function getPartyState() {
  return sendRequest('api/mm/party', 'GET')
}

export async function createPartyInvite(userHash, mode) {
  return sendRequest('api/mm/party/invite', 'POST', {
    userId: userHash,
    mode,
  })
}

export async function acceptPartyInvite(leaderHash) {
  return sendRequest('api/mm/party/invite/accept', 'POST', {
    leaderId: leaderHash,
  })
}

export async function declinePartyInvite(leaderHash) {
  return sendRequest('api/mm/party/invite/decline', 'POST', {
    leaderId: leaderHash,
  })
}

export async function leaveParty() {
  return sendRequest('api/mm/party/leave', 'POST')
}

export async function disbandParty() {
  return sendRequest('api/mm/party/disband', 'POST')
}

export async function kickPartyMember(memberHash) {
  return sendRequest('api/mm/party/kick', 'POST', {
    memberUserId: memberHash,
  })
}

export async function promotePartyMember(memberHash) {
  return sendRequest('api/mm/party/promote', 'POST', {
    newLeaderUserId: memberHash,
  })
}

export async function startPartySearch(partyHash) {
  const { getSessionId } = await import('@/api/matchmaking.js')
  return sendRequest('api/mm/party/search/start', 'POST', {
    partyHash,
    sessionId: getSessionId(),
  })
}

export async function cancelPartySearch() {
  const { getSessionId } = await import('@/api/matchmaking.js')
  return sendRequest('api/mm/party/search/cancel', 'POST', {
    sessionId: getSessionId(),
  })
}
