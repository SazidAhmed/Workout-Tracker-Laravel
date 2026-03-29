import { describe, expect, it, vi } from 'vitest'

const postMock = vi.fn()
const putMock = vi.fn()
const deleteMock = vi.fn()

vi.mock('@/lib/api', () => ({
  api: {
    post: (...args) => postMock(...args),
    put: (...args) => putMock(...args),
    delete: (...args) => deleteMock(...args),
  },
}))

import {
  completeSession,
  createExercise,
  createProgram,
  createSession,
  deleteProgramDay,
  updateProgram,
  updateSession,
  updateUser,
} from '@/services/crud'

describe('crud service endpoint contracts', () => {
  it('targets expected create/update/delete endpoints', async () => {
    const payload = { foo: 'bar' }

    await createProgram(payload)
    await updateProgram(12, payload)
    await deleteProgramDay(42)
    await createSession(payload)
    await updateSession(19, payload)
    await completeSession(19)
    await createExercise(payload)
    await updateUser(7, payload)

    expect(postMock).toHaveBeenCalledWith('/programs', payload)
    expect(putMock).toHaveBeenCalledWith('/programs/12', payload)
    expect(deleteMock).toHaveBeenCalledWith('/program-days/42')
    expect(postMock).toHaveBeenCalledWith('/workout-sessions', payload)
    expect(putMock).toHaveBeenCalledWith('/workout-sessions/19', payload)
    expect(postMock).toHaveBeenCalledWith('/workout-sessions/19/complete')
    expect(postMock).toHaveBeenCalledWith('/exercises', payload)
    expect(putMock).toHaveBeenCalledWith('/users/7', payload)
  })
})
