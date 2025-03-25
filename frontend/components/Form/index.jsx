import { createContext, useMemo, useState, useTransition } from 'react'
import PropTypes from 'prop-types'

const Form = ({ onSubmit, validationSchema, children }) => {
  const [clientState, setClientState] = useState(null)
  const [isPending, startTransition] = useTransition()

  const handleSubmit = (e) => {
    e.preventDefault()

    startTransition(async () => {
      let formData = new FormData(e.currentTarget)
      if (validationSchema) {
        const data = {}
        formData.forEach((value, key) => {
          if (!Reflect.has(data, key)) {
            data[key] = value

            return
          }

          if (!Array.isArray(data[key])) {
            data[key] = [data[key]]
          }

          data[key].push(value)
        })

        const response = validationSchema.safeParse(data)

        if (!response.success) {
          const errors = response.error.flatten().fieldErrors
          setClientState({ errors })

          return
        }

        formData = response.data
      }

      setClientState(null)

      try {
        await onSubmit(formData)
      } catch (e) {
        if (e.errors) {
          setClientState({
            errors: e.errors,
          })

          return
        }

        console.error(e)
        // TODO show toast
      }
    })
  }

  const context = useMemo(() => {
    return {
      errors: clientState?.errors || {},
      isPending,
    }
  }, [clientState, isPending])

  return (
    <form onSubmit={handleSubmit} noValidate>
      <FormContext.Provider value={context}>{children}</FormContext.Provider>
    </form>
  )
}

Form.propTypes = {
  onSubmit: PropTypes.func.isRequired,
  validationSchema: PropTypes.object,
}

export default Form

export const FormContext = createContext({})
