import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-ctl border text-sm font-medium whitespace-nowrap transition-colors duration-100 disabled:pointer-events-none disabled:opacity-55 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [@media(pointer:coarse)]:min-h-11 [@media(pointer:coarse)]:min-w-11 aria-pressed:border-primary aria-pressed:bg-sel aria-[current=page]:border-primary aria-[current=page]:bg-sel",
  {
    variants: {
      variant: {
        default:
          "border-line bg-raised text-ink hover:border-edge hover:bg-hover",
        primary:
          "border-transparent bg-primary text-primary-foreground hover:brightness-110 focus-visible:outline-ink",
        quiet:
          "border-transparent bg-transparent text-ink hover:bg-hover",
        danger:
          "border-line bg-raised text-bad-ink hover:border-edge hover:bg-hover",
        link: "border-transparent text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-7 px-2.5",
        sm: "h-6 px-2 text-xs",
        icon: "size-7 px-0",
        "icon-sm": "size-6 px-0 text-xs",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
