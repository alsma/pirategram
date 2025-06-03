"use client"

import { useState } from "react"
import { useFriendsStore } from "@/store/friends-store"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Users, UserPlus, Search, MoreVertical, Eye, Swords, UserMinus, UserCheck, UserX } from "lucide-react"

import placeholderImg from '@/assets/placeholder.svg'
import { useToast } from '@/hooks/use-toast.js'
import { useToastStore } from '@/store/toast-store.js'

export default function FriendsSidebar() {
  const { addSimpleSuccessToast } = useToastStore()
  const { friends, requests, addFriend, acceptRequest, rejectRequest, removeFriend } = useFriendsStore()
  const [searchQuery, setSearchQuery] = useState("")

  const handleAddFriend = (e) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      addFriend(searchQuery)
      addSimpleSuccessToast(`Friend request sent to ${searchQuery}`)
      setSearchQuery("")
    }
  }

  const getStatusIcon = (status) => {
    switch (status) {
      case "online":
        return <span className="h-2.5 w-2.5 bg-ember-light rounded-full glow-ember" />
      case "in-game":
        return <Swords className="h-3 w-3 text-burnt" />
      case "offline":
        return <span className="h-2.5 w-2.5 bg-gray-500 rounded-full" />
      default:
        return <span className="h-2.5 w-2.5 bg-gray-500 rounded-full" />
    }
  }

  return (
    <div className="h-full flex flex-col">
      <div className="p-4 border-b border-ember/20">
        <h2 className="text-xl font-semibold text-ember-light flex items-center">
          <Users className="mr-2 h-5 w-5" /> Friends
        </h2>
      </div>

      <Tabs defaultValue="friends" className="flex-1 flex flex-col">
        <TabsList className="grid grid-cols-3 p-1 mx-4 mt-4 bg-gray-800/70 rounded-lg">
          <TabsTrigger
            value="friends"
            className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Friends
          </TabsTrigger>
          <TabsTrigger
            value="requests"
            className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Requests
          </TabsTrigger>
          <TabsTrigger
            value="search"
            className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Search
          </TabsTrigger>
        </TabsList>

        <TabsContent value="friends" className="flex-1 overflow-y-auto p-4">
          {friends.length > 0 ? (
            <div className="space-y-2">
              {friends.map((friend, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-2 rounded-lg panel-texture border border-ember/10 hover:border-ember/30"
                >
                  <div className="flex items-center">
                    <Avatar className="h-8 w-8 mr-2 border border-ember/30">
                      <AvatarImage src={friend.avatar || placeholderImg} />
                      <AvatarFallback className="bg-gray-800 text-xs">{friend.handle.substring(0, 2)}</AvatarFallback>
                    </Avatar>
                    <div>
                      <div className="font-medium text-gray-200">{friend.handle}</div>
                      <div className="flex items-center text-xs text-gray-400">
                        {getStatusIcon(friend.status)}
                        <span className="ml-1 capitalize">{friend.status}</span>
                      </div>
                    </div>
                  </div>

                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon" className="h-8 w-8">
                        <MoreVertical className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="bg-gray-800 border-ember/20">
                      <DropdownMenuItem className="cursor-pointer flex items-center text-gray-200">
                        <Swords className="mr-2 h-4 w-4 text-burnt" />
                        <span>Invite to Party</span>
                      </DropdownMenuItem>
                      <DropdownMenuItem className="cursor-pointer flex items-center text-gray-200">
                        <Eye className="mr-2 h-4 w-4 text-slate" />
                        <span>Spectate</span>
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className="cursor-pointer flex items-center text-ember"
                        onClick={() => removeFriend(friend.id)}
                      >
                        <UserMinus className="mr-2 h-4 w-4" />
                        <span>Unfriend</span>
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              ))}
            </div>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-center p-4">
              <Users className="h-12 w-12 text-gray-500 mb-4" />
              <h3 className="text-lg font-medium text-gray-300 mb-2">No Friends Yet</h3>
              <p className="text-gray-400 text-sm">Add friends to play together and see their online status.</p>
            </div>
          )}
        </TabsContent>

        <TabsContent value="requests" className="flex-1 overflow-y-auto p-4">
          {requests.length > 0 ? (
            <div className="space-y-2">
              {requests.map((request, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-2 rounded-lg panel-texture border border-burnt/20"
                >
                  <div className="flex items-center">
                    <Avatar className="h-8 w-8 mr-2 border border-burnt/30">
                      <AvatarImage src={request.avatar || placeholderImg} />
                      <AvatarFallback className="bg-gray-800 text-xs">{request.handle.substring(0, 2)}</AvatarFallback>
                    </Avatar>
                    <div className="font-medium text-gray-200">{request.handle}</div>
                  </div>

                  <div className="flex space-x-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-green-400 hover:text-green-300 hover:bg-green-900/20"
                      onClick={() => acceptRequest(request.id)}
                    >
                      <UserCheck className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-ember hover:text-ember-light hover:bg-ember/20"
                      onClick={() => rejectRequest(request.id)}
                    >
                      <UserX className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-center p-4">
              <UserPlus className="h-12 w-12 text-gray-500 mb-4" />
              <h3 className="text-lg font-medium text-gray-300 mb-2">No Friend Requests</h3>
              <p className="text-gray-400 text-sm">When someone sends you a friend request, it will appear here.</p>
            </div>
          )}
        </TabsContent>

        <TabsContent value="search" className="flex-1 p-4">
          <form onSubmit={handleAddFriend} className="mb-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search by handle or email"
                className="pl-10 bg-gray-700/50 border-gray-600 focus:border-ember focus:ring-1 focus:ring-ember/50"
              />
              <Button
                type="submit"
                className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 bg-ember hover:bg-ember-light"
                disabled={!searchQuery.trim()}
              >
                <UserPlus className="h-4 w-4" />
              </Button>
            </div>
          </form>

          <div className="panel-texture border border-ember/20 rounded-lg p-4 text-sm text-gray-300">
            <p>Search tips:</p>
            <ul className="list-disc list-inside mt-2 space-y-1 text-gray-400">
              <li>Enter a full handle (e.g., Pirate#1234)</li>
              <li>Or search by email address</li>
              <li>Friend requests expire after 7 days</li>
            </ul>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  )
}
