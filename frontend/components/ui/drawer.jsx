"use client"

import { X } from "lucide-react"
import { Button } from "@/components/ui/button"

export function Drawer({ open, onOpenChange, children }) {
  return (
    <>
      {/* Backdrop */}
      {open && <div className="fixed inset-0 bg-black/50 z-40" onClick={() => onOpenChange(false)} />}

      {/* Drawer */}
      <div
        className={`
          fixed bottom-0 left-0 right-0 z-50 bg-gray-800 border-t border-ember/20 rounded-t-2xl shadow-xl transform transition-transform duration-300 ease-in-out
          ${open ? "translate-y-0" : "translate-y-full"}
        `}
      >
        <div className="flex justify-between items-center p-4 border-b border-ember/20">
          <div className="w-8" />
          <div className="h-1 w-16 bg-gray-600 rounded-full mx-auto" />
          <Button variant="ghost" size="icon" className="h-8 w-8 text-gray-400" onClick={() => onOpenChange(false)}>
            <X className="h-5 w-5" />
          </Button>
        </div>

        {children}
      </div>
    </>
  )
}

export const DrawerPortal = ({ children }) => <>{children}</>

export const DrawerOverlay = () => null

export const DrawerTrigger = ({ children }) => <>{children}</>

export const DrawerClose = ({ children }) => <>{children}</>

export const DrawerContent = ({ children }) => <div>{children}</div>

export const DrawerHeader = ({ children }) => <div>{children}</div>

export const DrawerFooter = ({ children }) => <div>{children}</div>

export const DrawerTitle = ({ children }) => <h1>{children}</h1>

export const DrawerDescription = ({ children }) => <p>{children}</p>
