import { sendRequest } from '@/api/index.js'

export async function getFriends() {
  return sendRequest('api/friends', 'GET')
}

export async function getFriendRequests() {
  return sendRequest('api/friends/requests', 'GET')
}

export async function searchUsers(query) {
  return sendRequest(`api/friends/search?query=${encodeURIComponent(query)}`, 'GET')
}

export async function sendFriendRequest(friendHash) {
  return sendRequest('api/friends/request', 'POST', {
    friendHash,
  })
}

export async function acceptFriendRequest(requesterHash) {
  return sendRequest('api/friends/request/accept', 'POST', {
    requesterHash,
  })
}

export async function declineFriendRequest(requesterHash) {
  return sendRequest('api/friends/request/decline', 'POST', {
    requesterHash,
  })
}

export async function removeFriend(friendHash) {
  return sendRequest('api/friends/remove', 'POST', {
    friendHash,
  })
}

export async function sendHeartbeat() {
  return sendRequest('api/friends/heartbeat', 'POST')
}

export async function sendAway() {
  return sendRequest('api/friends/away', 'POST')
}
